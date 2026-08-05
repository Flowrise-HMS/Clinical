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
                'code_search' => $dx->icd_entity_id
                    ? 'who:'.$dx->icd_entity_id
                    : ($dx->diagnosis_code_id ? 'local:'.$dx->diagnosis_code_id : null),
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
        if ($type instanceof DiagnosisType) {
            return $type;
        }

        if (is_string($type) && DiagnosisType::tryFrom($type)) {
            return DiagnosisType::from($type);
        }

        return DiagnosisType::Primary;
    }

    protected function resolveCertainty(DiagnosisCertainty|string|null $certainty): DiagnosisCertainty
    {
        if ($certainty instanceof DiagnosisCertainty) {
            return $certainty;
        }

        if (is_string($certainty) && DiagnosisCertainty::tryFrom($certainty)) {
            return DiagnosisCertainty::from($certainty);
        }

        return DiagnosisCertainty::Provisional;
    }

    /**
     * @param  array<int, array<string, mixed>>  $diagnoses
     */
    public function assertValidRows(array $diagnoses): void
    {
        foreach ($diagnoses as $index => $dx) {
            $hasCode = filled($dx['diagnosis_code_id'] ?? null)
                || filled($dx['icd_entity_id'] ?? null)
                || filled($dx['code_search'] ?? null);
            $hasDescription = filled($dx['description'] ?? null) || filled($dx['label'] ?? null);

            if (! $hasCode && ! $hasDescription) {
                throw ValidationException::withMessages([
                    "diagnoses.{$index}.description" => 'Each diagnosis needs a name or ICD code.',
                ]);
            }
        }
    }
}
