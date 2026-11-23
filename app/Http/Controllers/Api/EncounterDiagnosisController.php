<?php

namespace Modules\Clinical\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Clinical\Classes\Services\DiagnosisService;
use Modules\Clinical\Enums\DiagnosisCertainty;
use Modules\Clinical\Enums\DiagnosisType;
use Modules\Clinical\Models\EncounterDiagnosis;

/**
 * @group Clinical
 *
 * Encounter diagnoses (ICD-11 / ICD-10). Deleting deactivates the diagnosis.
 */
class EncounterDiagnosisController extends ClinicalApiController
{
    protected string $branchScope = 'via:encounter';

    public function __construct(protected DiagnosisService $diagnoses) {}

    public static function model(): string
    {
        return EncounterDiagnosis::class;
    }

    public function fields(): array
    {
        return ['id', 'encounter_id', 'patient_id', 'diagnosis_code_id', 'icd_code', 'icd10_code', 'icd_entity_id', 'description',
            'type', 'certainty', 'is_new_case', 'is_active', 'notes', 'ordered_by', 'created_at'];
    }

    public function filters(): array
    {
        return ['encounter_id' => 'encounter_id', 'patient_id' => 'patient_id', 'is_active' => 'is_active', 'type' => 'type'];
    }

    public function searchable(): array
    {
        return ['description', 'icd_code', 'icd10_code'];
    }

    public function storeRules(): array
    {
        return [
            'encounter_id' => ['required', 'uuid'],
            'diagnosis_code_id' => ['nullable', 'uuid', 'exists:diagnosis_codes,id'],
            'description' => ['required_without:diagnosis_code_id', 'nullable', 'string', 'max:500'],
            'icd_code' => ['nullable', 'string', 'max:50'],
            'icd10_code' => ['nullable', 'string', 'max:20'],
            'type' => ['nullable', Rule::enum(DiagnosisType::class)],
            'certainty' => ['nullable', Rule::enum(DiagnosisCertainty::class)],
            'is_new_case' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function updateRules(?Model $record = null): array
    {
        return [
            'type' => ['sometimes', Rule::enum(DiagnosisType::class)],
            'certainty' => ['sometimes', Rule::enum(DiagnosisCertainty::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function createRecord(array $data): Model
    {
        $encounter = $this->encounter($data['encounter_id']);
        $before = EncounterDiagnosis::query()->where('encounter_id', $encounter->id)->pluck('id');

        $this->diagnoses->record($encounter->patient, [collect($data)->except('encounter_id')->all()], $encounter->id);

        $created = EncounterDiagnosis::query()
            ->where('encounter_id', $encounter->id)
            ->whereNotIn('id', $before)
            ->latest()
            ->first();

        return $created ?? throw new \InvalidArgumentException('The diagnosis needs a description or a known diagnosis code.');
    }

    protected function deleteRecord(Model $record): void
    {
        $record->update(['is_active' => false]);
    }
}
