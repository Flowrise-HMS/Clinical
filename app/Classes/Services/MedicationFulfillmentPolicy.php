<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Models\InvoiceLine;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\MedicationRoute;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\PrescriptionDetail;

class MedicationFulfillmentPolicy
{
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
        ?string $route = null,
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
        $detail = $item->prescriptionDetail;
        if (! $detail || ! $this->requiresMar($detail)) {
            return false;
        }

        if ($item->isTerminal()) {
            return false;
        }

        $encounter = $item->serviceRequest?->encounter;
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
            $given = $this->countGivenDoses($item);
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
        $detail = $item->prescriptionDetail;
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

    public function requiresPaymentBeforeMarOrDispense(RequestItem $item): bool
    {
        if (! config('clinical.mar_payment.require_before_mar', true)) {
            return false;
        }

        $encounter = $item->serviceRequest?->encounter;
        if ($encounter?->type === EncounterType::EMERGENCY
            && config('clinical.mar_payment.emergency_exempt', true)) {
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
        //todo: Makae this configurable via settings later on
        return false;
    }

    public function isPaidFor(RequestItem $item): bool
    {
        if (! class_exists(InvoiceLine::class) || ! Schema::hasTable('invoice_lines')) {
            return ! ($item->service?->requires_payment_before ?? false);
        }

        $line = InvoiceLine::query()
            ->where('billable_type', $item::class)
            ->where('billable_id', $item->id)
            ->first();

        if (! $line) {
            return ! ($item->service?->requires_payment_before ?? false);
        }

        return $line->line_status === InvoiceLineStatus::Paid;
    }

    public function shouldCompleteOnDispense(RequestItem $item): bool
    {
        $detail = $item->prescriptionDetail;

        if ($detail === null) {
            return true;
        }

        return $this->requiresDispense($detail);
    }

    public function shouldCompleteOnMar(RequestItem $item): bool
    {
        $detail = $item->prescriptionDetail;
        if (! $detail || ! $this->requiresMar($detail)) {
            return false;
        }

        if ($detail->total_administrations !== null) {
            return $this->countGivenDoses($item) >= $detail->total_administrations;
        }

        return $detail->course_end_at !== null && now()->gt($detail->course_end_at);
    }

    public function countGivenDoses(RequestItem $item): int
    {
        return (int) $item->medicationAdministrations()
            ->where('status', MedicationAdministrationStatus::GIVEN)
            ->sum('quantity_given');
    }

    public function countConsumedSlots(RequestItem $item): int
    {
        return $item->medicationAdministrations()->count();
    }

    public function requiresWitness(PrescriptionDetail $detail, RequestItem $item): bool
    {
        $medication = Medication::query()->where('service_id', $item->service_id)->first();

        return $medication?->controlled_schedule !== null;
    }

    public function isControlledMedication(RequestItem $item): bool
    {
        $medication = Medication::query()->where('service_id', $item->service_id)->first();

        return $medication?->controlled_schedule !== null;
    }

    protected function isParenteralRoute(?string $route): bool
    {
        if ($route === null) {
            return false;
        }

        $enum = MedicationRoute::tryFrom($route);

        return in_array($enum, [MedicationRoute::IV, MedicationRoute::IM, MedicationRoute::SC], true);
    }
}
