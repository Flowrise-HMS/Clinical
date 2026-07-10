<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Models\Allergy;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Contracts\WardMedicationConsumptionContract;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Models\Medication;

class MedicationAdministrationService
{
    public function __construct(
        protected MedicationFulfillmentPolicy $policy,
        protected MedicationDoseScheduleService $scheduleService,
        protected WardMedicationConsumptionContract $wardMedicationConsumption,
    ) {}

    public function administerBatch(array $administrations, ?string $notes = null, ?User $user = null): array
    {
        $user = $user ?? Auth::user();
        $errors = [];
        $created = [];

        foreach ($administrations as $admin) {
            if (! ($admin['selected'] ?? true)) {
                continue;
            }

            $item = RequestItem::with(['prescriptionDetail', 'serviceRequest.encounter', 'service'])
                ->find($admin['request_item_id'] ?? null);

            if (! $item) {
                continue;
            }

            try {
                $this->administer($item, $admin, $notes, $user);
                $created[] = $item->service?->name ?? 'Unknown';
            } catch (\InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    public function administer(RequestItem $item, array $data, ?string $notes = null, ?User $user = null): MedicationAdministration
    {
        $user = $user ?? Auth::user();
        $detail = $item->prescriptionDetail;

        if (! $detail) {
            throw new \InvalidArgumentException("{$item->service?->name}: missing prescription details.");
        }

        if (! $this->policy->requiresMar($detail)) {
            throw new \InvalidArgumentException("{$item->service?->name}: staff MAR is not required for take-home orders.");
        }

        if (! $this->policy->canRecordMar($item, $user)) {
            if ($this->policy->requiresPaymentBeforeMarOrDispense($item) && ! $this->policy->isPaidFor($item)) {
                throw new \InvalidArgumentException("{$item->service?->name}: payment is required before recording this dose.");
            }

            throw new \InvalidArgumentException("{$item->service?->name}: cannot record dose for this order.");
        }

        if ($user && ! $user->can('administer_medication')) {
            throw new \InvalidArgumentException('You do not have permission to record medication administration.');
        }

        $status = MedicationAdministrationStatus::tryFrom($data['status'] ?? 'given')
            ?? MedicationAdministrationStatus::GIVEN;

        if ($detail->prn && $status === MedicationAdministrationStatus::GIVEN && empty($data['prn_reason'])) {
            throw new \InvalidArgumentException("{$item->service?->name}: PRN reason is required.");
        }

        if (in_array($status, [MedicationAdministrationStatus::OMITTED, MedicationAdministrationStatus::REFUSED], true)
            && empty($data['omission_reason'])) {
            throw new \InvalidArgumentException("{$item->service?->name}: omission/refusal reason is required.");
        }

        if ($this->policy->requiresWitness($detail, $item) && $status === MedicationAdministrationStatus::GIVEN) {
            if (! filter_var($data['witness_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw new \InvalidArgumentException("{$item->service?->name}: witness attestation is required for controlled medications.");
            }
        }

        $quantityGiven = $status === MedicationAdministrationStatus::GIVEN
            ? (int) ($data['quantity_given'] ?? 1)
            : 0;

        if ($status === MedicationAdministrationStatus::GIVEN && $detail->total_administrations) {
            $remaining = $detail->total_administrations - $this->policy->countGivenDoses($item);
            if ($quantityGiven > $remaining) {
                throw new \InvalidArgumentException(
                    "{$item->service?->name}: exceeds remaining {$remaining} dose(s)"
                );
            }
        }

        $startedAt = isset($data['started_at'])
            ? (is_string($data['started_at']) && strlen($data['started_at']) <= 5
                ? now()->format('Y-m-d').' '.$data['started_at']
                : $data['started_at'])
            : now();

        $endedAt = isset($data['ended_at'])
            ? (is_string($data['ended_at']) && strlen($data['ended_at']) <= 5
                ? now()->format('Y-m-d').' '.$data['ended_at']
                : $data['ended_at'])
            : $startedAt;

        if ($endedAt && $startedAt && $endedAt < $startedAt) {
            throw new \InvalidArgumentException(
                "{$item->service?->name}: ended time cannot be before started time"
            );
        }

        $nextSlot = $this->scheduleService->getNextDueSlot($item);
        if ($nextSlot && $status === MedicationAdministrationStatus::GIVEN
            && $this->scheduleService->hasDuplicateGivenForSlot($item, $nextSlot->sequence)) {
            throw new \InvalidArgumentException(
                "{$item->service?->name}: a dose has already been recorded for this time slot."
            );
        }

        return DB::transaction(function () use ($item, $startedAt, $endedAt, $data, $notes, $user, $status, $quantityGiven, $detail) {
            $administration = MedicationAdministration::create([
                'request_item_id' => $item->id,
                'administered_by' => $user->id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'quantity_given' => $quantityGiven,
                'dose_unit_id' => $data['dose_unit_id'] ?? $detail->dose_unit_id,
                'status' => $status,
                'witness_confirmed' => filter_var($data['witness_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'omission_reason' => $data['omission_reason'] ?? null,
                'prn_reason' => $data['prn_reason'] ?? null,
                'notes' => $notes,
            ]);

            $this->scheduleService->markSlotForAdministration($item, $administration);

            if ($item->isPending()) {
                $item->markAsInProgress();
            }

            if ($this->policy->shouldCompleteOnMar($item)) {
                $item->markAsFulfilled($user->id);
            }

            $this->recordWardConsumptionIfApplicable($item, $administration, $quantityGiven);

            return $administration;
        });
    }

    protected function recordWardConsumptionIfApplicable(
        RequestItem $item,
        MedicationAdministration $administration,
        int $quantityGiven,
    ): void {
        if ($quantityGiven <= 0 || $administration->status !== MedicationAdministrationStatus::GIVEN) {
            return;
        }

        $detail = $item->prescriptionDetail;
        if ($detail === null || $detail->administration_context !== AdministrationContext::IN_FACILITY) {
            return;
        }

        $serviceRequest = $item->serviceRequest;
        $departmentId = $serviceRequest?->encounter?->department_id;
        $branchId = $serviceRequest?->branch_id;

        if ($departmentId === null || $branchId === null || $item->service_id === null) {
            return;
        }

        $medicationId = Medication::query()
            ->where('service_id', $item->service_id)
            ->value('id');

        if ($medicationId === null) {
            return;
        }

        $this->wardMedicationConsumption->consumeMedicationFromWard(
            medicationId: (string) $medicationId,
            branchId: (string) $branchId,
            departmentId: (string) $departmentId,
            qty: $quantityGiven,
            reference: $administration,
        );
    }

    public function discontinueCourse(RequestItem $item, User $user, string $reason): void
    {
        if ($item->isTerminal()) {
            return;
        }

        $item->update([
            'notes' => trim(($item->notes ?? '')."\nDiscontinued: {$reason}"),
        ]);
        $item->markAsFulfilled($user->id);
    }

    public function convertToTakeHome(RequestItem $item, User $user): void
    {
        $detail = $item->prescriptionDetail;
        if (! $detail || ! $detail->isInFacility()) {
            throw new \InvalidArgumentException('Only in-facility orders can be converted to take-home.');
        }

        $detail->update([
            'administration_context' => AdministrationContext::TAKE_HOME,
        ]);
    }

    public function getPendingItems(?string $patientId = null, bool $inFacilityOnly = true): Collection
    {
        return RequestItem::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('prescriptionDetail')
            ->when($inFacilityOnly, fn ($q) => $q->whereHas(
                'prescriptionDetail',
                fn ($q) => $q->where('administration_context', AdministrationContext::IN_FACILITY)
            ))
            ->when($patientId, fn ($q) => $q->whereHas('serviceRequest', fn ($q) => $q->where('patient_id', $patientId)))
            ->with(['service', 'prescriptionDetail', 'medicationAdministrations', 'serviceRequest.patient', 'serviceRequest.encounter'])
            ->get()
            ->filter(fn (RequestItem $item) => $this->policy->canRecordMar($item));
    }

    public function getTakeHomeItems(?string $patientId = null): Collection
    {
        return RequestItem::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('prescriptionDetail', fn ($q) => $q->where('administration_context', AdministrationContext::TAKE_HOME))
            ->when($patientId, fn ($q) => $q->whereHas('serviceRequest', fn ($q) => $q->where('patient_id', $patientId)))
            ->with(['service', 'prescriptionDetail', 'serviceRequest.patient'])
            ->get()
            ->filter(fn (RequestItem $item) => $this->policy->canDispense($item));
    }

    public function getRemainingDoses(RequestItem $item): int|string
    {
        $detail = $item->prescriptionDetail;
        if (! $detail || ! $detail->total_administrations) {
            return '∞';
        }

        $given = $this->policy->countGivenDoses($item);

        return max(0, $detail->total_administrations - $given);
    }

    /**
     * @return Collection<int, Allergy>
     */
    public function getPatientAllergiesForMar(RequestItem $item): Collection
    {
        $patientId = $item->serviceRequest?->patient_id;
        if (! $patientId) {
            return collect();
        }

        return Allergy::query()
            ->active()
            ->forPatient($patientId)
            ->get();
    }
}
