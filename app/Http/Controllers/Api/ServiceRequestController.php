<?php

namespace Modules\Clinical\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Clinical\Classes\Services\ServiceRequestService;
use Modules\Clinical\Enums\RequestPriority;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Http\Resources\FieldListTransformer;

/**
 * @group Clinical
 *
 * Service requests (orders for labs, imaging, procedures, medicines) with their items.
 */
class ServiceRequestController extends ClinicalApiController
{
    protected array $with = ['items'];

    public function __construct(protected ServiceRequestService $requests) {}

    public static function model(): string
    {
        return ServiceRequest::class;
    }

    public function fields(): array
    {
        return ['id', 'request_number', 'patient_id', 'encounter_id', 'status', 'priority', 'notes', 'ordered_by', 'created_at', 'updated_at'];
    }

    public function filters(): array
    {
        return ['patient_id' => 'patient_id', 'encounter_id' => 'encounter_id', 'status' => 'status', 'priority' => 'priority'];
    }

    public function searchable(): array
    {
        return ['request_number'];
    }

    public function storeRules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'encounter_id' => ['nullable', 'uuid'],
            'priority' => ['nullable', Rule::enum(RequestPriority::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.service_id' => ['required', 'uuid', 'exists:services,id'],
            'items.*.variant_id' => ['nullable', 'uuid'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function updateRules(?Model $record = null): array
    {
        return [
            'priority' => ['sometimes', Rule::enum(RequestPriority::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    protected function createRecord(array $data): Model
    {
        $patient = $this->patient($data['patient_id']);

        return $this->requests->createForPatient(
            $patient,
            $data['items'],
            $this->encounterIdFor($patient, $data['encounter_id'] ?? null),
            isset($data['priority']) ? enum_from(RequestPriority::class, $data['priority']) : null,
            $data['notes'] ?? null,
        );
    }

    protected function present(Model $record, Request $request): array
    {
        return [
            ...parent::present($record, $request),
            'items' => $record->items->map(fn (RequestItem $item): array => FieldListTransformer::using($item, [
                'id', 'service_id', 'service_variant_id', 'quantity', 'unit_price', 'total_price', 'status', 'fulfilled_at',
            ])->toArray($request))->values()->all(),
        ];
    }

    /**
     * Submit the request for fulfilment.
     */
    public function submit(string $id): JsonResponse
    {
        return $this->act($id, 'update', fn (ServiceRequest $serviceRequest) => $this->requests->submitRequest($serviceRequest));
    }

    /**
     * Cancel the request and its open items.
     *
     * @bodyParam reason string Why it was cancelled. Example: Duplicate order.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->act($id, 'update', fn (ServiceRequest $serviceRequest) => $this->requests->cancelRequest($serviceRequest, $data['reason'] ?? null));
    }
}
