<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Support\AppSettings;
use Modules\Core\Support\ModuleAvailability;
use Modules\Core\Support\OptionalClass;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\MedicationRoute;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\PrescriptionDetail;

class MedicationFulfillmentPolicy
{
    /** @var array<string, ?Medication> */
    protected array $medicationByServiceId = [];

    public function requiresMar(PrescriptionDetail $detail): bool
    {
        return $detail->administration_context === AdministrationContext::IN_FACILITY;
    }

    public function requiresDispense(PrescriptionDetail $detail): bool
    {
        return $detail->administration_context === AdministrationContext::TAKE_HOME;
    }

    public function defaultAdministrationContext(
        ?Encounter $encounter,
        MedicationRoute|string|null $route = null,
        bool $administerInFacilityFlag = false,
    ): AdministrationContext {
        if (! $encounter || ! $encounter->isActive()) {
            return AdministrationContext::TAKE_HOME;
        }

        if (in_array($encounter->type, [EncounterType::EMERGENCY, EncounterType::INPATIENT], true)) {
            return AdministrationContext::IN_FACILITY;
        }

        if ($administerInFacilityFlag || $this->isParenteralRoute($route)) {
            return AdministrationContext::IN_FACILITY;
        }

        return AdministrationContext::TAKE_HOME;
    }

    public function canRecordMar(RequestItem $item, ?User $user = null): bool
    {
        $detail = $this->prescriptionDetailFor($item);
        if (! $detail || ! $this->requiresMar($detail)) {
            return false;
        }

        if ($item->isTerminal()) {
            return false;
        }

        $encounter = $item->loadMissing('serviceRequest.encounter')->serviceRequest?->encounter;
        if (! $encounter || ! $encounter->isActive()) {
            return false;
        }

        if ($detail->course_end_at && now()->gt($detail->course_end_at)) {
            return false;
        }

        if ($this->requiresPaymentBeforeMarOrDispense($item) && ! $this->isPaidFor($item)) {
            return false;
        }

        if ($detail->total_administrations !== null) {
            $given = $this->givenDosesCount($item);
            if ($given >= $detail->total_administrations) {
                return false;
            }
        }

        if ($user && ! $user->can('administer_medication')) {
            return false;
        }

        return true;
    }

    public function canDispense(RequestItem $item, ?User $user = null): bool
    {
        if ($user !== null && ! $this->isPharmacyStaff($user)) {
            return false;
        }

        $detail = $this->prescriptionDetailFor($item);
        if (! $detail) {
            return true;
        }

        if ($item->isTerminal()) {
            return false;
        }

        if ($this->requiresMar($detail)) {
            return true;
        }

        if ($this->requiresPaymentBeforeMarOrDispense($item) && ! $this->isPaidFor($item)) {
            return false;
        }

        return $this->requiresDispense($detail);
    }

    public function isPharmacyStaff(User $user): bool
    {
        return $user->can('dispense_medication');
    }

    public function requiresPaymentBeforeMarOrDispense(RequestItem $item): bool
    {
        try {
            $clinical = app(AppSettings::class)->clinical();
            if (! $clinical->mar_require_payment_before) {
                return false;
            }
            $emergencyExempt = $clinical->mar_emergency_exempt;
        } catch (\Throwable) {
            if (! config('clinical.mar_payment.require_before_mar', true)) {
                return false;
            }
            $emergencyExempt = config('clinical.mar_payment.emergency_exempt', true);
        }

        $item->loadMissing(['serviceRequest.encounter', 'service']);

        $encounter = $item->serviceRequest?->encounter;
        if ($encounter?->type === EncounterType::EMERGENCY && $emergencyExempt) {
            return false;
        }

        if ($item->service?->requires_payment_before) {
            return true;
        }

        // if (! class_exists(InvoiceLine::class) || ! Schema::hasTable('invoice_lines')) {
        //     return false;
        // }

        // return InvoiceLine::query()
        //     ->where('billable_type', $item::class)
        //     ->where('billable_id', $item->id)
        //     ->exists();
        // todo: Makae this configurable via settings later on
        return false;
    }

    public function isPaidFor(RequestItem $item): bool
    {
        $item->loadMissing('service');

        if (! ModuleAvailability::billingEnabled() || ! Schema::hasTable('invoice_lines')) {
            return ! ($item->service?->requires_payment_before ?? false);
        }

        $line = OptionalClass::when(
            'Modules\\Billing\\Models\\InvoiceLine',
            fn (string $invoiceLineClass) => $invoiceLineClass::query()
                ->where('billable_type', $item::class)
                ->where('billable_id', $item->id)
                ->first(),
            'Billing',
        );

        if (! $line) {
            return ! ($item->service?->requires_payment_before ?? false);
        }

        $paidStatus = OptionalClass::when(
            'Modules\\Billing\\Enums\\InvoiceLineStatus',
            fn (string $statusClass) => $statusClass::Paid,
            'Billing',
        );

        return $paidStatus !== null && $line->line_status === $paidStatus;
    }

    public function shouldCompleteOnDispense(RequestItem $item): bool
    {
        $detail = $this->prescriptionDetailFor($item);

        if ($detail === null) {
            return true;
        }

        return $this->requiresDispense($detail);
    }

    public function shouldCompleteOnMar(RequestItem $item): bool
    {
        $detail = $this->prescriptionDetailFor($item);
        if (! $detail || ! $this->requiresMar($detail)) {
            return false;
        }

        if ($detail->total_administrations !== null) {
            return $this->givenDosesCount($item) >= $detail->total_administrations;
        }

        return $detail->course_end_at !== null && now()->gt($detail->course_end_at);
    }

    public function countGivenDoses(RequestItem $item): int
    {
        return (int) $item->medicationAdministrations()
            ->where('status', MedicationAdministrationStatus::GIVEN)
            ->sum('quantity_given');
    }

    public function givenDosesCount(RequestItem $item): int
    {
        if (isset($item->given_doses)) {
            return (int) $item->given_doses;
        }

        return $this->countGivenDoses($item);
    }

    public function countConsumedSlots(RequestItem $item): int
    {
        return $item->medicationAdministrations()->count();
    }

    public function requiresWitness(PrescriptionDetail $detail, RequestItem $item): bool
    {
        $medication = $this->medicationForService((string) $item->service_id);

        return $medication?->controlled_schedule !== null;
    }

    public function isControlledMedication(RequestItem $item): bool
    {
        $medication = $this->medicationForService((string) $item->service_id);

        return $medication?->controlled_schedule !== null;
    }

    protected function prescriptionDetailFor(RequestItem $item): ?PrescriptionDetail
    {
        return $item->loadMissing('prescriptionDetail')->prescriptionDetail;
    }

    protected function medicationForService(string $serviceId): ?Medication
    {
        if (! array_key_exists($serviceId, $this->medicationByServiceId)) {
            $this->medicationByServiceId[$serviceId] = Medication::query()
                ->where('service_id', $serviceId)
                ->first();
        }

        return $this->medicationByServiceId[$serviceId];
    }

    protected function isParenteralRoute(MedicationRoute|string|null $route): bool
    {
        $enum = $this->normalizeRoute($route);

        return in_array($enum, [MedicationRoute::IV, MedicationRoute::IM, MedicationRoute::SC], true);
    }

    protected function normalizeRoute(MedicationRoute|string|null $route): ?MedicationRoute
    {
        return enum_try_from(MedicationRoute::class, $route);
    }
}
