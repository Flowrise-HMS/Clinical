<?php

namespace Modules\Clinical\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Models\Encounter;

/**
 * @group Clinical
 *
 * Patient encounters (visits and admissions). Workflow transitions use the
 * action endpoints; admission, transfer and discharge stay in the panel.
 */
class EncounterController extends ClinicalApiController
{
    protected string $defaultSort = '-created_at';

    public function __construct(protected EncounterService $encounters) {}

    public static function model(): string
    {
        return Encounter::class;
    }

    public function fields(): array
    {
        return ['id', 'encounter_number', 'patient_id', 'type', 'status', 'priority', 'chief_complaint', 'coverage_type',
            'location_id', 'department_id', 'bed_id', 'admitted_at', 'discharged_at', 'expected_discharge_at',
            'discharge_disposition', 'created_at', 'updated_at'];
    }

    public function filters(): array
    {
        return ['patient_id' => 'patient_id', 'status' => 'status', 'type' => 'type', 'location_id' => 'location_id'];
    }

    public function searchable(): array
    {
        return ['encounter_number', 'chief_complaint'];
    }

    public function sortable(): array
    {
        return ['created_at', 'admitted_at', 'discharged_at'];
    }

    public function storeRules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(EncounterType::class)],
            'priority' => ['nullable', Rule::enum(EncounterPriority::class)],
            'chief_complaint' => ['nullable', 'string', 'max:2000'],
            'location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
        ];
    }

    public function updateRules(?Model $record = null): array
    {
        return [
            'priority' => ['sometimes', Rule::enum(EncounterPriority::class)],
            'chief_complaint' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'location_id' => ['sometimes', 'nullable', 'uuid', 'exists:locations,id'],
            'department_id' => ['sometimes', 'nullable', 'uuid', 'exists:departments,id'],
        ];
    }

    protected function createRecord(array $data): Model
    {
        return $this->encounters->createForPatient(
            $this->patient($data['patient_id']),
            EncounterType::from($data['type']),
            $data['chief_complaint'] ?? null,
            isset($data['priority']) ? EncounterPriority::from($data['priority']) : null,
            $data['location_id'] ?? null,
            $data['department_id'] ?? null,
        );
    }

    /**
     * Mark the patient as arrived.
     */
    public function arrive(string $id): JsonResponse
    {
        return $this->act($id, 'update', fn (Encounter $encounter) => $this->encounters->arrive($encounter, auth()->id()));
    }

    /**
     * Start the encounter (in progress).
     */
    public function start(string $id): JsonResponse
    {
        return $this->act($id, 'update', fn (Encounter $encounter) => $this->encounters->startEncounter($encounter));
    }

    /**
     * Complete an active outpatient encounter.
     *
     * @bodyParam notes string Closing notes. Example: Reviewed and discharged home.
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:5000']]);

        return $this->act($id, 'update', fn (Encounter $encounter) => $this->encounters->completeEncounter($encounter, $data['notes'] ?? null, auth()->id()));
    }

    /**
     * Cancel an encounter.
     *
     * @bodyParam reason string Why it was cancelled. Example: Registered in error.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->act($id, 'update', fn (Encounter $encounter) => $this->encounters->cancelEncounter($encounter, $data['reason'] ?? null));
    }
}
