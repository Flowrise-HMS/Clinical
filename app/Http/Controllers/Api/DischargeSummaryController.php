<?php

namespace Modules\Clinical\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Modules\Clinical\Classes\Services\DischargeSummaryService;
use Modules\Clinical\Enums\DischargeCondition;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Models\DischargeSummary;
use Modules\Clinical\Models\Encounter;

/**
 * @group Clinical
 *
 * Discharge summaries: drafted per encounter, edited while unsigned, then signed.
 */
class DischargeSummaryController extends ClinicalApiController
{
    public function __construct(protected DischargeSummaryService $summaries) {}

    public static function model(): string
    {
        return DischargeSummary::class;
    }

    public function fields(): array
    {
        return ['id', 'encounter_id', 'patient_id', 'status', 'admission_diagnosis', 'discharge_diagnoses', 'presenting_complaint',
            'hospital_course', 'procedures', 'condition_at_discharge', 'discharge_medications', 'instructions', 'diet', 'activity',
            'follow_up_at', 'follow_up_notes', 'disposition', 'authored_by', 'signed_by', 'signed_at', 'created_at', 'updated_at'];
    }

    public function filters(): array
    {
        return ['encounter_id' => 'encounter_id', 'patient_id' => 'patient_id', 'status' => 'status'];
    }

    public function storeRules(): array
    {
        return ['encounter_id' => ['required', 'uuid']];
    }

    public function updateRules(?Model $record = null): array
    {
        return [
            'admission_diagnosis' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'discharge_diagnoses' => ['sometimes', 'nullable', 'array'],
            'presenting_complaint' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'hospital_course' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'procedures' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'condition_at_discharge' => ['sometimes', 'nullable', Rule::enum(DischargeCondition::class)],
            'discharge_medications' => ['sometimes', 'nullable', 'array'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'diet' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'activity' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'follow_up_at' => ['sometimes', 'nullable', 'date'],
            'follow_up_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'disposition' => ['sometimes', 'nullable', Rule::enum(DischargeDisposition::class)],
        ];
    }

    /**
     * The policy ties discharge summaries to encounter access and has no
     * viewAny; listing therefore requires viewAny on encounters.
     */
    protected function authorizeResource(string $ability, ?Model $record = null): void
    {
        if ($ability === 'viewAny') {
            $this->authorizeApi('viewAny', Encounter::class);

            return;
        }

        parent::authorizeResource($ability, $record);
    }

    protected function createRecord(array $data): Model
    {
        $encounter = $this->encounter($data['encounter_id']);

        if (! $encounter->isInpatient()) {
            throw new \DomainException('Discharge summaries are written for inpatient encounters.');
        }

        return $this->summaries->draftFor($encounter, auth()->id());
    }

    protected function updateRecord(Model $record, array $data): Model
    {
        return $this->summaries->update($record, $data, auth()->id());
    }

    /**
     * Sign the summary (locks it; later changes are amendments in the panel).
     */
    public function sign(string $id): JsonResponse
    {
        return $this->act($id, 'sign', fn (DischargeSummary $summary) => $this->summaries->sign($summary, auth()->user()));
    }
}
