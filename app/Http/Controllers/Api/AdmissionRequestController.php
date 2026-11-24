<?php

namespace Modules\Clinical\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Models\AdmissionRequest;

/**
 * @group Clinical
 *
 * Admission requests to a ward; the ward accepts (assigning a bed) or rejects.
 */
class AdmissionRequestController extends ClinicalApiController
{
    public function __construct(protected AdtService $adt) {}

    public static function model(): string
    {
        return AdmissionRequest::class;
    }

    public function fields(): array
    {
        return ['id', 'encounter_id', 'patient_id', 'requested_ward_id', 'requested_bed_id', 'assigned_bed_id', 'status', 'notes',
            'requested_by', 'requested_at', 'expires_at', 'decided_by', 'decided_at', 'decision_notes', 'created_at'];
    }

    public function filters(): array
    {
        return ['status' => 'status', 'encounter_id' => 'encounter_id', 'requested_ward_id' => 'requested_ward_id'];
    }

    public function storeRules(): array
    {
        return [
            'encounter_id' => ['required', 'uuid'],
            'ward_id' => ['required', 'uuid', 'exists:locations,id'],
            'bed_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Same rules as the panel: reading follows the encounter, deciding needs
     * update on it, and the requester may withdraw their own pending request.
     */
    protected function authorizeResource(string $ability, ?Model $record = null): void
    {
        if ($ability === 'cancel' && $record instanceof AdmissionRequest && $record->isCancellableBy(auth()->id())) {
            return;
        }

        $this->authorizeThroughEncounter($ability, $record?->encounter);
    }

    protected function createRecord(array $data): Model
    {
        $encounter = $this->encounter($data['encounter_id']);
        $this->authorizeThroughEncounter('update', $encounter);

        return $this->adt->requestAdmission($encounter, $data['ward_id'], $data['bed_id'] ?? null, $data['notes'] ?? null);
    }

    /**
     * Accept the request and admit the patient to a bed.
     *
     * @bodyParam bed_id string required The bed (location) to assign. Example: 9d0c6b0e-...
     * @bodyParam notes string Example: Bed 4 prepared.
     */
    public function accept(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'bed_id' => ['required', 'uuid', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->act($id, 'accept', function (AdmissionRequest $admission) use ($data): AdmissionRequest {
            $this->adt->acceptAdmission($admission, $data['bed_id'], $data['notes'] ?? null);

            return $admission->refresh();
        });
    }

    /**
     * Reject the request.
     *
     * @bodyParam reason string required Example: No bed available.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->act($id, 'reject', fn (AdmissionRequest $admission) => $this->adt->rejectAdmission($admission, $data['reason']));
    }

    /**
     * Withdraw the request.
     *
     * @bodyParam reason string Example: Patient transferred elsewhere.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->act($id, 'cancel', fn (AdmissionRequest $admission) => $this->adt->cancelAdmissionRequest($admission, $data['reason'] ?? null));
    }
}
