<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Enums\DischargeSummaryStatus;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Models\DischargeSummary;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Support\ModuleAvailability;

/**
 * Drafts, edits, signs and amends the structured discharge summary. A signed
 * summary is also written to the encounter as a discharge note so it shows
 * up wherever clinical notes do.
 */
class DischargeSummaryService
{
    public function __construct(protected ClinicalNoteService $notes) {}

    /**
     * Returns the encounter's summary, creating a pre-filled draft if none exists.
     */
    public function draftFor(Encounter $encounter, ?int $authorId = null): DischargeSummary
    {
        $existing = $encounter->dischargeSummary()->first();

        if ($existing !== null) {
            return $existing;
        }

        $encounter->loadMissing(['diagnoses.diagnosisCode']);

        $diagnoses = $encounter->diagnoses->map(fn (EncounterDiagnosis $diagnosis): array => [
            'description' => $diagnosis->description ?: $diagnosis->diagnosisCode?->description,
            'icd10_code' => $diagnosis->icd10_code ?: $diagnosis->icd_code,
            'type' => $this->value($diagnosis->type),
            'certainty' => $this->value($diagnosis->certainty),
        ])->values()->all();

        $primary = collect($diagnoses)->firstWhere('type', 'primary') ?? ($diagnoses[0] ?? null);

        return DischargeSummary::query()->create([
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'branch_id' => $encounter->branch_id,
            'admission_diagnosis' => $primary['description'] ?? null,
            'discharge_diagnoses' => $diagnoses,
            'presenting_complaint' => $encounter->chief_complaint,
            'discharge_medications' => $this->takeHomeMedications($encounter),
            'disposition' => $encounter->discharge_disposition,
            'status' => DischargeSummaryStatus::DRAFT,
            'authored_by' => $authorId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DischargeSummary $summary, array $data, ?int $userId = null): DischargeSummary
    {
        if ($summary->isSigned()) {
            throw new \InvalidArgumentException(__('A signed discharge summary must be amended, not edited.'));
        }

        $summary->fill($this->editable($data));

        if ($summary->authored_by === null && $userId !== null) {
            $summary->authored_by = $userId;
        }

        $summary->save();

        return $summary->fresh();
    }

    public function sign(DischargeSummary $summary, User $user): DischargeSummary
    {
        if (! $user->can('sign_discharge_summary')) {
            throw new \InvalidArgumentException(__('You are not allowed to sign discharge summaries.'));
        }

        if ($summary->isSigned()) {
            return $summary;
        }

        return DB::transaction(function () use ($summary, $user): DischargeSummary {
            $summary->forceFill([
                'status' => DischargeSummaryStatus::SIGNED,
                'signed_by' => $user->id,
                'signed_at' => now(),
                'authored_by' => $summary->authored_by ?? $user->id,
            ])->save();

            $this->recordNote($summary, $user);

            return $summary->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function amend(DischargeSummary $summary, array $data, User $user, ?string $reason = null): DischargeSummary
    {
        if (! $summary->isSigned()) {
            return $this->update($summary, $data, $user->id);
        }

        if (! $user->can('sign_discharge_summary')) {
            throw new \InvalidArgumentException(__('You are not allowed to amend discharge summaries.'));
        }

        return DB::transaction(function () use ($summary, $data, $user, $reason): DischargeSummary {
            $amendments = $summary->metadata['amendments'] ?? [];
            $amendments[] = [
                'by' => $user->id,
                'at' => now()->toIso8601String(),
                'reason' => $reason,
                'changed' => array_keys($this->editable($data)),
            ];

            $summary->fill($this->editable($data));
            $summary->forceFill([
                'status' => DischargeSummaryStatus::AMENDED,
                'signed_by' => $user->id,
                'signed_at' => now(),
                'metadata' => array_merge($summary->metadata ?? [], ['amendments' => $amendments]),
            ])->save();

            $this->recordNote($summary, $user, amended: true);

            return $summary->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function editable(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'admission_diagnosis', 'discharge_diagnoses', 'presenting_complaint', 'hospital_course', 'procedures',
            'condition_at_discharge', 'discharge_medications', 'instructions', 'diet', 'activity',
            'follow_up_at', 'follow_up_notes', 'follow_up_provider_id', 'disposition',
        ]));
    }

    protected function recordNote(DischargeSummary $summary, User $user, bool $amended = false): void
    {
        $encounter = $summary->encounter()->with('patient')->first();

        if ($encounter?->patient === null) {
            return;
        }

        $lines = array_filter([
            $summary->admission_diagnosis ? __('Admission diagnosis: :value', ['value' => $summary->admission_diagnosis]) : null,
            collect($summary->discharge_diagnoses ?? [])->pluck('description')->filter()->isNotEmpty()
                ? __('Discharge diagnoses: :value', ['value' => collect($summary->discharge_diagnoses)->pluck('description')->filter()->implode('; ')])
                : null,
            $summary->hospital_course ? __('Hospital course: :value', ['value' => strip_tags($summary->hospital_course)]) : null,
            $summary->condition_at_discharge ? __('Condition at discharge: :value', ['value' => $summary->condition_at_discharge->getLabel()]) : null,
            $summary->instructions ? __('Instructions: :value', ['value' => strip_tags($summary->instructions)]) : null,
            $summary->follow_up_at ? __('Follow-up: :value', ['value' => $summary->follow_up_at->toDayDateTimeString()]) : null,
        ]);

        $this->notes->record($encounter->patient, [
            'note_type' => NoteType::DISCHARGE,
            'status' => NoteStatus::SIGNED,
            'subject' => $amended ? __('Discharge summary (amended)') : __('Discharge summary'),
            'content' => implode("\n", $lines),
        ], $encounter->id, $user->id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function takeHomeMedications(Encounter $encounter): array
    {
        if (! ModuleAvailability::pharmacyEnabled()) {
            return [];
        }

        return RequestItem::query()
            ->whereHas('serviceRequest', fn ($q) => $q->where('encounter_id', $encounter->id))
            ->whereHas('prescriptionDetail', fn ($q) => $q->where('administration_context', 'take_home'))
            ->whereNotIn('status', ['cancelled'])
            ->with(['service', 'prescriptionDetail'])
            ->get()
            ->map(function (RequestItem $item): array {
                $detail = $item->prescriptionDetail;

                return [
                    'drug' => $item->service?->name,
                    'dose' => $detail?->dosage ?: ($detail?->dose_amount !== null ? rtrim(rtrim(number_format((float) $detail->dose_amount, 4, '.', ''), '0'), '.') : null),
                    'frequency' => $this->value($detail?->frequency),
                    'route' => $this->value($detail?->route),
                    'duration_days' => $detail?->duration_days,
                    'instructions' => $detail?->instructions,
                ];
            })
            ->values()
            ->all();
    }

    protected function value(mixed $enumOrString): ?string
    {
        if ($enumOrString === null) {
            return null;
        }

        return is_object($enumOrString) && isset($enumOrString->value) ? (string) $enumOrString->value : (string) $enumOrString;
    }
}
