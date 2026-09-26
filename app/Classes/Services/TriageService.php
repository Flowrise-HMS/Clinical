<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Clinical\Classes\Support\Triage\SatsDiscriminators;
use Modules\Clinical\Classes\Support\Triage\TewsResult;
use Modules\Clinical\Enums\AvpuLevel;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Enums\TriageAgeBand;
use Modules\Clinical\Enums\TriageCategory;
use Modules\Clinical\Enums\TriageDisposition;
use Modules\Clinical\Enums\TriageMobility;
use Modules\Clinical\Enums\VitalSignType;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\TriageAssessment;
use Modules\Clinical\Models\VitalSign;
use Modules\Patient\Models\Patient;

/**
 * Records South African Triage Scale (SATS) assessments.
 *
 * The final category is the most severe of the TEWS colour and the clinical
 * discriminators, unless a senior clinician overrides it with a reason. The
 * encounter's priority follows the latest assessment, so re-triage re-orders the queue.
 */
class TriageService
{
    public const VITAL_FIELDS = ['respiratory_rate', 'heart_rate', 'systolic_bp', 'diastolic_bp', 'temperature', 'spo2'];

    public function __construct(
        protected SatsTewsCalculator $calculator,
        protected VitalSignService $vitalSignService,
        protected ClinicalNoteService $clinicalNoteService,
        protected EncounterService $encounterService,
    ) {}

    public static function ageBandFor(?Patient $patient): TriageAgeBand
    {
        return TriageAgeBand::fromDateOfBirth($patient?->date_of_birth);
    }

    /**
     * Starting form state for a triage: the TEWS version and default next step, plus the
     * most recent vitals already taken, because vitals are often recorded before triage.
     * Vitals come from the encounter, or from the patient's last 24 hours when the
     * encounter has none; older readings are not carried over.
     *
     * @return array<string, mixed>
     */
    public function prefillFor(?Patient $patient, ?Encounter $encounter = null): array
    {
        $state = [
            'age_band' => self::ageBandFor($patient)->value,
            'disposition' => TriageDisposition::CONSULTATION->value,
            'trauma' => false,
            'chief_complaint' => $encounter?->chief_complaint,
        ];

        foreach (TriageCategory::clinical() as $category) {
            $state['discriminators_'.$category->value] = [];
        }

        return [...$state, ...$this->vitalsPrefillFor($patient, $encounter)];
    }

    /**
     * @return array<string, mixed>
     */
    public function vitalsPrefillFor(?Patient $patient, ?Encounter $encounter = null): array
    {
        if ($patient === null) {
            return [];
        }

        $vitals = $encounter?->vitalSigns()->latest('recorded_at')->first()
            ?? VitalSign::query()
                ->where('patient_id', $patient->id)
                ->where('recorded_at', '>=', now()->subDay())
                ->latest('recorded_at')
                ->first();

        if ($vitals === null) {
            return [];
        }

        $state = [
            'source_vital_sign_id' => $vitals->id,
            'source_vitals_recorded_at' => $vitals->recorded_at?->toIso8601String(),
        ];

        foreach (self::VITAL_FIELDS as $field) {
            $state[$field] = $this->normalizeVital($vitals->{$field}, $field);
        }

        return $state;
    }

    /**
     * Live score for a form state, without saving anything.
     *
     * @param  array<string, mixed>  $data
     * @return array{tews: TewsResult, discriminator_category: ?TriageCategory, category: TriageCategory}
     */
    public function preview(array $data): array
    {
        $band = enum_try_from(TriageAgeBand::class, $data['age_band'] ?? null) ?? TriageAgeBand::ADULT;
        $tews = $this->score($band, $data);
        $discriminatorCategory = SatsDiscriminators::categoryFor($band, $this->discriminatorKeys($band, $data));

        return [
            'tews' => $tews,
            'discriminator_category' => $discriminatorCategory,
            'category' => TriageCategory::mostSevere($tews->category, $discriminatorCategory) ?? $tews->category,
        ];
    }

    /**
     * @param  array<string, mixed>  $data  age_band, vitals (see VITAL_FIELDS), mobility, avpu, trauma,
     *                                      discriminators, override_category, override_reason,
     *                                      disposition, chief_complaint, notes
     */
    public function assess(Encounter $encounter, array $data, ?int $triagedBy = null): TriageAssessment
    {
        if (! $encounter->status->isActive() && $encounter->status !== EncounterStatus::PLANNED) {
            throw new InvalidArgumentException('Only an open encounter can be triaged.');
        }

        $override = enum_try_from(TriageCategory::class, $data['override_category'] ?? null);

        if ($override !== null && blank($data['override_reason'] ?? null)) {
            throw new InvalidArgumentException('A reason is required to override the triage category.');
        }

        $triagedBy ??= Auth::id();

        return DB::transaction(function () use ($encounter, $data, $override, $triagedBy): TriageAssessment {
            $patient = $encounter->patient;
            $band = enum_try_from(TriageAgeBand::class, $data['age_band'] ?? null) ?? self::ageBandFor($patient);
            $tews = $this->score($band, $data);
            $discriminators = $this->discriminatorKeys($band, $data);
            $discriminatorCategory = SatsDiscriminators::categoryFor($band, $discriminators);
            $final = $override ?? TriageCategory::mostSevere($tews->category, $discriminatorCategory) ?? $tews->category;

            $vitalSign = null;
            $vitals = array_filter(
                array_intersect_key($data, array_flip(self::VITAL_FIELDS)),
                fn (mixed $value): bool => filled($value),
            );

            $source = filled($data['source_vital_sign_id'] ?? null)
                ? VitalSign::query()->whereKey($data['source_vital_sign_id'])->where('patient_id', $patient->id)->first()
                : null;

            if ($source !== null && $this->matchesVitals($source, $data)) {
                // Prefilled vitals used unchanged: link the existing reading instead of duplicating it.
                $vitalSign = $source;
            } elseif ($vitals !== []) {
                $vitalSign = $this->vitalSignService->record($patient, $vitals, $encounter->id, VitalSignType::TRIAGE, $triagedBy);
            }

            $assessment = TriageAssessment::query()->create([
                'encounter_id' => $encounter->id,
                'patient_id' => $patient->id,
                'branch_id' => $encounter->branch_id ?? $patient->branch_id,
                'vital_sign_id' => $vitalSign?->id,
                'age_band' => $band,
                'mobility' => enum_try_from(TriageMobility::class, $data['mobility'] ?? null),
                'avpu' => enum_try_from(AvpuLevel::class, $data['avpu'] ?? null),
                'trauma' => (bool) ($data['trauma'] ?? false),
                'tews_score' => $tews->score,
                'tews_breakdown' => $tews->breakdown,
                'discriminators' => $discriminators,
                'tews_category' => $tews->category,
                'discriminator_category' => $discriminatorCategory,
                'override_category' => $override,
                'override_reason' => $override ? $data['override_reason'] : null,
                'final_category' => $final,
                'disposition' => enum_try_from(TriageDisposition::class, $data['disposition'] ?? null) ?? TriageDisposition::defaultFor($final),
                'notes' => filled($data['notes'] ?? null) ? $data['notes'] : null,
                'triaged_by' => $triagedBy,
                'triaged_at' => now(),
            ]);

            if (filled($data['notes'] ?? null)) {
                $this->clinicalNoteService->record($patient, [
                    'note_type' => NoteType::TRIAGE,
                    'status' => NoteStatus::SIGNED,
                    'subject' => 'Triage - '.$final->shortLabel().' (TEWS '.$tews->score.')',
                    'content' => $data['notes'],
                ], $encounter->id, $triagedBy);
            }

            if (filled($data['chief_complaint'] ?? null) && blank($encounter->chief_complaint)) {
                $encounter->update(['chief_complaint' => $data['chief_complaint']]);
            }

            $this->applyToEncounter($encounter->refresh(), $final);

            return $assessment;
        });
    }

    /**
     * Moves an arrived (or planned walk-in) encounter to TRIAGED with the mapped priority;
     * a re-triage of an encounter already in progress only updates the priority.
     */
    protected function applyToEncounter(Encounter $encounter, TriageCategory $category): void
    {
        $priority = $category->toEncounterPriority();

        if ($encounter->status === EncounterStatus::PLANNED) {
            $encounter = $this->encounterService->arrive($encounter);
        }

        if ($encounter->status === EncounterStatus::ARRIVED) {
            $this->encounterService->triage($encounter, $priority);

            return;
        }

        if ($encounter->priority !== $priority) {
            $encounter->update(['priority' => $priority]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function matchesVitals(VitalSign $vitals, array $data): bool
    {
        foreach (self::VITAL_FIELDS as $field) {
            if ($this->normalizeVital($vitals->{$field}, $field) !== $this->normalizeVital($data[$field] ?? null, $field)) {
                return false;
            }
        }

        return true;
    }

    protected function normalizeVital(mixed $value, string $field): int|float|null
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return $field === 'temperature' ? round((float) $value, 1) : (int) round((float) $value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function score(TriageAgeBand $band, array $data): TewsResult
    {
        return $this->calculator->calculate(
            $band,
            $data,
            enum_try_from(TriageMobility::class, $data['mobility'] ?? null),
            enum_try_from(AvpuLevel::class, $data['avpu'] ?? null),
            (bool) ($data['trauma'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    protected function discriminatorKeys(TriageAgeBand $band, array $data): array
    {
        $keys = (array) ($data['discriminators'] ?? []);

        foreach (TriageCategory::clinical() as $category) {
            $keys = [...$keys, ...(array) ($data['discriminators_'.$category->value] ?? [])];
        }

        return SatsDiscriminators::filter($band, array_values(array_unique($keys)));
    }
}
