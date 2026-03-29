<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Enums\RequestPriority;
use Modules\Clinical\Enums\RequestStatus;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceVariant;
use Modules\Patient\Models\Patient;

class ServiceRequestService
{
    public function __construct(
        protected BranchService $branchService
    ) {}

    public function createForPatient(
        Patient $patient,
        array $serviceIds,
        ?string $encounterId = null,
        ?RequestPriority $priority = null,
        ?string $notes = null,
        ?int $orderedBy = null
    ): ServiceRequest {
        return DB::transaction(function () use ($patient, $serviceIds, $encounterId, $priority, $notes, $orderedBy) {
            $request = ServiceRequest::create([
                'patient_id' => $patient->id,
                'encounter_id' => $encounterId,
                'branch_id' => $patient->branch_id,
                'priority' => $priority ?? RequestPriority::default(),
                'notes' => $notes,
                'ordered_by' => $orderedBy ?? auth()->id(),
                'created_by' => $orderedBy ?? auth()->id(),
            ]);

            foreach ($serviceIds as $serviceData) {
                $serviceId = is_array($serviceData) ? $serviceData['service_id'] : $serviceData;
                $variantId = is_array($serviceData) ? ($serviceData['variant_id'] ?? null) : null;
                $quantity = is_array($serviceData) ? ($serviceData['quantity'] ?? 1) : 1;

                $this->addItem($request, $serviceId, $variantId, $quantity);
            }

            return $request->load('items.service');
        });
    }

    public function createForGuest(
        string $guestName,
        string $guestPhone,
        array $serviceIds,
        ?string $guestEmail = null,
        ?RequestPriority $priority = null,
        ?string $notes = null,
        ?string $branchId = null,
        ?int $orderedBy = null
    ): ServiceRequest {
        return DB::transaction(function () use ($guestName, $guestPhone, $serviceIds, $guestEmail, $priority, $notes, $branchId, $orderedBy) {
            $request = ServiceRequest::create([
                'patient_id' => null,
                'encounter_id' => null,
                'branch_id' => $branchId ?? $this->branchService->getDefaultBranchId(),
                'priority' => $priority ?? RequestPriority::default(),
                'notes' => $notes,
                'guest_name' => $guestName,
                'guest_phone' => $guestPhone,
                'guest_email' => $guestEmail,
                'ordered_by' => $orderedBy ?? auth()->id(),
                'created_by' => $orderedBy ?? auth()->id(),
            ]);

            foreach ($serviceIds as $serviceData) {
                $serviceId = is_array($serviceData) ? $serviceData['service_id'] : $serviceData;
                $variantId = is_array($serviceData) ? ($serviceData['variant_id'] ?? null) : null;
                $quantity = is_array($serviceData) ? ($serviceData['quantity'] ?? 1) : 1;

                $this->addItem($request, $serviceId, $variantId, $quantity);
            }

            return $request->load('items.service');
        });
    }

    public function addItem(
        ServiceRequest $request,
        string $serviceId,
        ?string $variantId = null,
        int $quantity = 1
    ): RequestItem {
        $service = Service::findOrFail($serviceId);
        $variant = $variantId ? ServiceVariant::find($variantId) : null;

        $unitPrice = $service->getDefaultPrice();

        if ($variant) {
            $unitPrice = $variant->getFinalPrice();
        }

        $item = $request->items()->create([
            'service_id' => $serviceId,
            'service_variant_id' => $variantId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity,
            'status' => RequestItemStatus::PENDING,
        ]);

        $this->checkRequestCompletion($request);

        return $item;
    }

    public function removeItem(RequestItem $item): void
    {
        $request = $item->serviceRequest;

        $item->delete();

        if ($request->items()->count() === 0) {
            $request->delete();

            return;
        }

        $this->checkRequestCompletion($request);
    }

    public function updateItemQuantity(RequestItem $item, int $quantity): RequestItem
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero');
        }

        $item->update([
            'quantity' => $quantity,
            'total_price' => $item->unit_price * $quantity,
        ]);

        return $item->fresh();
    }

    public function cancelItem(RequestItem $item, ?string $reason = null): RequestItem
    {
        $item->update([
            'status' => RequestItemStatus::CANCELLED,
            'notes' => $reason ? ($item->notes ? "{$item->notes}\n{$reason}" : $reason) : $item->notes,
        ]);

        $this->checkRequestCompletion($item->serviceRequest);

        return $item->fresh();
    }

    public function submitRequest(ServiceRequest $request): ServiceRequest
    {
        if ($request->status !== RequestStatus::DRAFT) {
            throw new \InvalidArgumentException('Only draft requests can be submitted');
        }

        if ($request->items()->count() === 0) {
            throw new \InvalidArgumentException('Cannot submit empty request');
        }

        $request->update(['status' => RequestStatus::ACTIVE]);

        return $request->fresh();
    }

    public function cancelRequest(ServiceRequest $request, ?string $reason = null): ServiceRequest
    {
        if ($request->isCompleted()) {
            throw new \InvalidArgumentException('Cannot cancel completed request');
        }

        DB::transaction(function () use ($request, $reason) {
            $request->update([
                'status' => RequestStatus::CANCELLED,
                'metadata' => array_merge($request->metadata ?? [], ['cancel_reason' => $reason]),
            ]);

            $request->items()
                ->where('status', RequestItemStatus::PENDING)
                ->update([
                    'status' => RequestItemStatus::CANCELLED,
                    'notes' => DB::raw("CONCAT(COALESCE(notes, ''), '\nCancelled: {$reason}')"),
                ]);
        });

        return $request->fresh();
    }

    public function markItemAsInProgress(RequestItem $item): RequestItem
    {
        if (! $item->isPending()) {
            throw new \InvalidArgumentException('Item is not in pending status');
        }

        $item->update(['status' => RequestItemStatus::IN_PROGRESS]);

        return $item->fresh();
    }

    public function getActiveRequests(?string $branchId = null): Collection
    {
        $query = ServiceRequest::active()
            ->with(['patient', 'items.service', 'orderedBy']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('priority')->orderBy('created_at')->get();
    }

    public function getRequestsByPatient(Patient $patient): Collection
    {
        return ServiceRequest::where('patient_id', $patient->id)
            ->with(['encounter', 'items.service', 'orderedBy'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function getRequestsByService(Service $service): Collection
    {
        return ServiceRequest::whereHas('items', fn ($q) => $q->where('service_id', $service->id))
            ->with(['patient', 'items' => fn ($q) => $q->where('service_id', $service->id)])
            ->orderByDesc('created_at')
            ->get();
    }

    public function getPendingItemsByRole(string $role): Collection
    {
        return RequestItem::pending()
            ->whereHas('service.roles', fn ($q) => $q->where('name', $role))
            ->with(['serviceRequest.patient', 'service'])
            ->get();
    }

    public function getRequestByNumber(string $requestNumber): ?ServiceRequest
    {
        return ServiceRequest::where('request_number', $requestNumber)
            ->with(['patient', 'encounter', 'items.service', 'orderedBy'])
            ->first();
    }

    protected function checkRequestCompletion(ServiceRequest $request): void
    {
        $hasPendingItems = $request->items()
            ->whereNotIn('status', [
                RequestItemStatus::COMPLETED->value,
                RequestItemStatus::CANCELLED->value,
            ])
            ->exists();

        if (! $hasPendingItems) {
            $request->update(['status' => RequestStatus::COMPLETED]);
        }
    }
}
