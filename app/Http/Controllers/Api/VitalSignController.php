<?php

namespace Modules\Clinical\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Clinical\Classes\Services\VitalSignService;
use Modules\Clinical\Enums\VitalSignType;
use Modules\Clinical\Models\VitalSign;

/**
 * @group Clinical
 *
 * Vital sign readings. Readings are immutable once recorded.
 */
class VitalSignController extends ClinicalApiController
{
    protected string $defaultSort = '-recorded_at';

    public function __construct(protected VitalSignService $vitals) {}

    public static function model(): string
    {
        return VitalSign::class;
    }

    public function fields(): array
    {
        return ['id', 'patient_id', 'encounter_id', 'type', 'recorded_at', 'recorded_by', 'position', 'systolic_bp', 'diastolic_bp',
            'heart_rate', 'respiratory_rate', 'temperature', 'spo2', 'weight', 'height', 'bmi', 'pain_level',
            'gcs_eye', 'gcs_verbal', 'gcs_motor', 'fbs', 'rbs', 'notes', 'created_at'];
    }

    public function filters(): array
    {
        return ['patient_id' => 'patient_id', 'encounter_id' => 'encounter_id', 'type' => 'type'];
    }

    public function sortable(): array
    {
        return ['recorded_at', 'created_at'];
    }

    public function storeRules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'encounter_id' => ['nullable', 'uuid'],
            'type' => ['nullable', Rule::enum(VitalSignType::class)],
            'recorded_at' => ['nullable', 'date', 'before_or_equal:now'],
            'systolic_bp' => ['nullable', 'integer', 'between:40,300'],
            'diastolic_bp' => ['nullable', 'integer', 'between:20,200'],
            'heart_rate' => ['nullable', 'integer', 'between:20,300'],
            'respiratory_rate' => ['nullable', 'integer', 'between:4,80'],
            'temperature' => ['nullable', 'numeric', 'between:25,45'],
            'spo2' => ['nullable', 'integer', 'between:30,100'],
            'weight' => ['nullable', 'numeric', 'between:0.2,500'],
            'height' => ['nullable', 'numeric', 'between:20,260'],
            'pain_level' => ['nullable', 'integer', 'between:0,10'],
            'gcs_eye' => ['nullable', 'integer', 'between:1,4'],
            'gcs_verbal' => ['nullable', 'integer', 'between:1,5'],
            'gcs_motor' => ['nullable', 'integer', 'between:1,6'],
            'fbs' => ['nullable', 'numeric', 'between:0,60'],
            'rbs' => ['nullable', 'numeric', 'between:0,60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function createRecord(array $data): Model
    {
        $patient = $this->patient($data['patient_id']);
        $encounterId = isset($data['encounter_id']) ? $this->encounter($data['encounter_id'])->getKey() : null;
        $type = isset($data['type']) ? VitalSignType::from($data['type']) : null;

        return $this->vitals->record($patient, collect($data)->except(['patient_id', 'encounter_id', 'type'])->all(), $encounterId, $type);
    }
}
