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
     * Make the encounter's active diagnoses match the submitted set (workspace form
     * source of truth). Rows that carry an `encounter_diagnosis_id` belonging to one
     * of this encounter's active diagnoses are updated in place, so re-saving an
     * edit never produces a new row; rows without one are created; active rows
     * missing from the submission are deactivated. An id from another encounter is
     * ignored and the row is treated as new.
     *
     * Pass `$loadedIds` (the rows the caller's form was showing) so that only rows the
     * user could have removed are retired. A diagnosis added meanwhile from the header
     * menu or by a colleague was never on the form, so its absence is not a removal.
     *
     * @param  array<int, array<string, mixed>>  $diagnoses
     * @param  list<string>|null  $loadedIds  null retires every active row not submitted
     */
    public function syncForEncounter(
        Patient $patient,
        array $diagnoses,
        string $encounterId,
        ?int $orderedBy = null,
        ?array $loadedIds = null,
    ): void {
        $diagnoses = $this->hydrateRows($diagnoses);
        $this->assertValidRows($diagnoses);

        DB::transaction(function () use ($patient, $diagnoses, $encounterId, $orderedBy, $loadedIds) {
            $existing = EncounterDiagnosis::query()
                ->where('encounter_id', $encounterId)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            $kept = [];
            $new = [];

            foreach ($diagnoses as $dx) {
                $rowId = $dx['encounter_diagnosis_id'] ?? null;

                if (! filled($rowId) || ! $existing->has((string) $rowId)) {
                    $new[] = $dx;

                    continue;
                }

                $attributes = $this->rowAttributes($dx);

                if ($attributes === null) {
                    continue;
                }

                /** @var EncounterDiagnosis $row */
                $row = $existing->get((string) $rowId);
                $row->fill($attributes)->save();
                $kept[] = $row->id;
            }

            EncounterDiagnosis::query()
                ->where('encounter_id', $encounterId)
                ->where('is_active', true)
                ->whereNotIn('id', $kept)
                ->when($loadedIds !== null, fn ($query) => $query->whereIn('id', $loadedIds))
                ->update(['is_active' => false]);

            $this->createRows($patient, $new, $encounterId, $orderedBy);
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
            $attributes = $this->rowAttributes($dx, $fallbackNotes);

            if ($attributes === null) {
                continue;
            }

            EncounterDiagnosis::create([
                ...$attributes,
                'encounter_id' => $encounterId,
                'patient_id' => $patient->id,
                'ordered_by' => $orderedBy ?? auth()->id(),
                'is_active' => true,
            ]);
        }
    }

    /**
     * Resolve one submitted row to the clinical attributes shared by create and
     * update. Returns null when the row has no usable description.
     *
     * @param  array<string, mixed>  $dx
     * @return array<string, mixed>|null
     */
    protected function rowAttributes(array $dx, ?string $fallbackNotes = null): ?array
    {
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
            return null;
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

        return [
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
        ];
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
                'encounter_diagnosis_id' => $dx->id,
                'diagnosis_code_id' => $dx->diagnosis_code_id,
                'code_search' => $dx->diagnosis_code_id
                    ? 'local:'.$dx->diagnosis_code_id
                    : ($dx->icd_entity_id ? 'who:'.$dx->icd_entity_id : null),
                'icd_code' => $dx->icd_code,
                'icd10_code' => $dx->icd10_code,
                'icd_entity_id' => $dx->icd_entity_id,
                'icd_uri' => $dx->icd_uri,
                'description' => $dx->description,
                'type' => enum_value($dx->type) ?? DiagnosisType::Primary->value,
                'is_new_case' => $dx->is_new_case ? '1' : '0',
                'certainty' => enum_value($dx->certainty) ?? DiagnosisCertainty::Provisional->value,
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

            // Form rows always carry the key (null until a pick), so test presence by value.
            if (! filled($dx['icd10_code'] ?? null) && $code->source !== 'who') {
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
