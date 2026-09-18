<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Clinical\Data\DiagnosisCodeSearchResult;
use Modules\Clinical\Enums\DiagnosisCertainty;
use Modules\Clinical\Enums\DiagnosisType;
use Modules\Clinical\Models\DiagnosisCode;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Patient\Models\Patient;

class DiagnosisService
{
    /**
     * Append diagnoses to an encounter (does not deactivate existing rows).
     *
     * @param  array<int, array<string, mixed>>  $diagnoses
     */
    public function record(
        Patient $patient,
        array $diagnoses,
        string $encounterId,
        ?int $orderedBy = null,
        ?string $notes = null,
    ): void {
        $diagnoses = $this->hydrateRows($diagnoses);

        DB::transaction(function () use ($patient, $diagnoses, $encounterId, $orderedBy, $notes) {
            $this->createRows($patient, $diagnoses, $encounterId, $orderedBy, $notes);
        });
    }

    /**
     * Replace active diagnoses for an encounter with the provided set (workspace form source of truth).
     *
     * @param  array<int, array<string, mixed>>  $diagnoses
     */
    public function syncForEncounter(
        Patient $patient,
        array $diagnoses,
        string $encounterId,
        ?int $orderedBy = null,
    ): void {
        $diagnoses = $this->hydrateRows($diagnoses);
        $this->assertValidRows($diagnoses);

        DB::transaction(function () use ($patient, $diagnoses, $encounterId, $orderedBy) {
            EncounterDiagnosis::query()
                ->where('encounter_id', $encounterId)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $this->createRows($patient, $diagnoses, $encounterId, $orderedBy);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $diagnoses
     */
    protected function createRows(
        Patient $patient,
        array $diagnoses,
        string $encounterId,
        ?int $orderedBy = null,
        ?string $fallbackNotes = null,
    ): void {
        foreach ($diagnoses as $dx) {
            $icdCode = $dx['icd_code'] ?? null;
            $icd10Code = $dx['icd10_code'] ?? null;
            $diagnosisCodeId = $dx['diagnosis_code_id'] ?? $dx['id'] ?? null;
            $description = $dx['description'] ?? $dx['label'] ?? null;
            $icdEntityId = $dx['icd_entity_id'] ?? null;
            $icdUri = $dx['icd_uri'] ?? null;

            if (filled($diagnosisCodeId)) {
                $code = DiagnosisCode::find($diagnosisCodeId);
                if ($code) {
                    $diagnosisCodeId = $code->id;
                    $icdCode ??= $code->code;
                    $description ??= $code->description;
                } else {
                    $diagnosisCodeId = null;
                }
            } else {
                $diagnosisCodeId = null;
            }

            if (! filled($description)) {
                continue;
            }

            if (! filled($diagnosisCodeId) && filled($icdEntityId)) {
                $diagnosisCodeId = app(IcdCatalogueService::class)->localId(
                    new DiagnosisCodeSearchResult(
                        localId: null,
                        code: $icdCode,
                        label: $description,
                        externalId: $icdEntityId,
                        uri: $icdUri,
                        source: 'who',
                    )
                );
            }

            $rowNotes = $dx['notes'] ?? $fallbackNotes;

            EncounterDiagnosis::create([
                'encounter_id' => $encounterId,
                'patient_id' => $patient->id,
                'diagnosis_code_id' => $diagnosisCodeId,
                'icd_entity_id' => $icdEntityId,
                'icd_uri' => $icdUri,
                'icd_code' => $icdCode,
                'icd10_code' => $icd10Code,
                'description' => $description,
                'notes' => is_string($rowNotes) ? $rowNotes : null,
                'type' => $this->resolveType($dx['type'] ?? null),
                'is_new_case' => filter_var($dx['is_new_case'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'certainty' => $this->resolveCertainty($dx['certainty'] ?? null),
                'ordered_by' => $orderedBy ?? auth()->id(),
                'is_active' => true,
            ]);
        }
    }

    /**
     * @return array{diagnoses: list<array<string, mixed>>, notes: string}
     */
    public function getForEncounter(string $encounterId): array
    {
        $records = EncounterDiagnosis::where('encounter_id', $encounterId)
            ->where('is_active', true)
            ->with('diagnosisCode')
            ->orderByRaw("CASE type WHEN 'primary' THEN 0 WHEN 'secondary' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->get();

        return [
            'diagnoses' => $records->map(fn (EncounterDiagnosis $dx): array => [
                'diagnosis_code_id' => $dx->diagnosis_code_id,
                'code_search' => $dx->diagnosis_code_id
                    ? 'local:'.$dx->diagnosis_code_id
                    : ($dx->icd_entity_id ? 'who:'.$dx->icd_entity_id : null),
                'icd_code' => $dx->icd_code,
                'icd10_code' => $dx->icd10_code,
                'icd_entity_id' => $dx->icd_entity_id,
                'icd_uri' => $dx->icd_uri,
                'description' => $dx->description,
                'type' => $dx->type?->value ?? DiagnosisType::Primary->value,
                'is_new_case' => $dx->is_new_case ? '1' : '0',
                'certainty' => $dx->certainty?->value ?? DiagnosisCertainty::Provisional->value,
                'notes' => $dx->notes,
            ])->all(),
            'notes' => $records->first()?->notes ?? '',
        ];
    }

    protected function resolveType(DiagnosisType|string|null $type): DiagnosisType
    {
        return enum_try_from(DiagnosisType::class, $type) ?? DiagnosisType::Primary;
    }

    protected function resolveCertainty(DiagnosisCertainty|string|null $certainty): DiagnosisCertainty
    {
        return enum_try_from(DiagnosisCertainty::class, $certainty) ?? DiagnosisCertainty::Provisional;
    }

    /**
     * Fill the code fields of rows that only carry a `code_search` selection
     * (`local:<id>` / `who:<entity id>`) from the local catalogue, so a save never
     * depends on request-scoped state from the search step.
     *
     * @param  array<int, array<string, mixed>>  $diagnoses
     * @return array<int, array<string, mixed>>
     */
    public function hydrateRows(array $diagnoses): array
    {
        foreach ($diagnoses as $index => $dx) {
            $selection = $dx['code_search'] ?? null;

            if (! is_string($selection) || $selection === '') {
                continue;
            }

            $needsHydration = ! filled($dx['description'] ?? null)
                || (! filled($dx['diagnosis_code_id'] ?? null) && ! filled($dx['icd_entity_id'] ?? null));

            if (! $needsHydration) {
                continue;
            }

            $code = $this->resolveSelection($selection);

            if ($code === null) {
                continue;
            }

            $dx['diagnosis_code_id'] = $dx['diagnosis_code_id'] ?? $code->id;
            $dx['description'] = filled($dx['description'] ?? null) ? $dx['description'] : $code->description;
            $dx['icd_code'] = $dx['icd_code'] ?? $code->code;
            $dx['icd_entity_id'] = $dx['icd_entity_id'] ?? $code->icd_entity_id;
            $dx['icd_uri'] = $dx['icd_uri'] ?? $code->icd_uri;

            if (! array_key_exists('icd10_code', $dx) && $code->source !== 'who') {
                $dx['icd10_code'] = $code->code;
            }

            $diagnoses[$index] = $dx;
        }

        return $diagnoses;
    }

    protected function resolveSelection(string $selection): ?DiagnosisCode
    {
        if (str_starts_with($selection, 'local:')) {
            return DiagnosisCode::query()->find(substr($selection, 6));
        }

        if (str_starts_with($selection, 'who:')) {
            return DiagnosisCode::query()->where('icd_entity_id', substr($selection, 4))->first();
        }

        return null;
    }

    /**
     * Every row must end up with a description: either typed by the clinician or
     * resolved from its ICD selection by hydrateRows(). Rows that fail here used to
     * be dropped silently after the existing diagnoses had already been deactivated.
     *
     * @param  array<int, array<string, mixed>>  $diagnoses
     */
    public function assertValidRows(array $diagnoses): void
    {
        foreach ($this->hydrateRows($diagnoses) as $index => $dx) {
            $hasDescription = filled($dx['description'] ?? null) || filled($dx['label'] ?? null);

            if ($hasDescription) {
                continue;
            }

            $hasSelection = filled($dx['diagnosis_code_id'] ?? null)
                || filled($dx['icd_entity_id'] ?? null)
                || filled($dx['code_search'] ?? null);

            throw ValidationException::withMessages([
                "diagnoses.{$index}.description" => $hasSelection
                    ? 'The selected ICD code could not be resolved. Search for it again or type the diagnosis name.'
                    : 'Each diagnosis needs a name or ICD code.',
            ]);
        }
    }
}
