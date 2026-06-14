<?php

namespace Modules\Clinical\Classes\Services;

use Carbon\Carbon;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Classes\Data\DoseSlot;
use Modules\Pharmacy\Classes\Services\PrescriptionScheduleCalculator;
use Modules\Pharmacy\Models\PrescriptionDetail;

class MedicationDoseScheduleService
{
    public function __construct(
        protected PrescriptionScheduleCalculator $calculator,
        protected MedicationFulfillmentPolicy $policy,
    ) {}

    /**
     * @return list<DoseSlot>
     */
    public function getSchedule(PrescriptionDetail $detail): array
    {
        return $this->calculator->buildDoseSchedule($detail);
    }

    public function getNextDueSlot(RequestItem $item): ?DoseSlot
    {
        $detail = $item->prescriptionDetail;
        if (! $detail || ! $this->policy->requiresMar($detail)) {
            return null;
        }

        $slots = $this->getSchedule($detail);
        if ($slots === []) {
            return null;
        }

        $givenSequences = $item->medicationAdministrations()
            ->where('status', MedicationAdministrationStatus::GIVEN)
            ->whereNotNull('dose_slot_sequence')
            ->pluck('dose_slot_sequence')
            ->all();

        foreach ($slots as $slot) {
            if (! in_array($slot->sequence, $givenSequences, true)) {
                return $slot;
            }
        }

        return null;
    }

    public function syncNextDoseAt(RequestItem $item): void
    {
        $detail = $item->prescriptionDetail;
        if (! $detail) {
            return;
        }

        $next = $this->getNextDueSlot($item);
        $detail->update(['next_dose_at' => $next?->dueAt]);
    }

    public function markSlotForAdministration(RequestItem $item, MedicationAdministration $administration): void
    {
        $detail = $item->prescriptionDetail;
        if (! $detail) {
            return;
        }

        $slots = $this->getSchedule($detail);
        if ($slots === []) {
            return;
        }

        $adminTime = Carbon::parse($administration->started_at);
        $graceMinutes = (int) config('clinical.mar_schedule.grace_minutes', 30);
        $bestSlot = null;
        $bestDiff = PHP_INT_MAX;

        foreach ($slots as $slot) {
            $diff = abs($adminTime->diffInMinutes($slot->dueAt, false));
            if ($diff <= $graceMinutes && $diff < $bestDiff) {
                $bestDiff = $diff;
                $bestSlot = $slot;
            }
        }

        if ($bestSlot === null) {
            $bestSlot = $this->getNextDueSlot($item);
        }

        if ($bestSlot !== null) {
            $administration->update(['dose_slot_sequence' => $bestSlot->sequence]);
        }

        $this->syncNextDoseAt($item);
    }

    public function hasDuplicateGivenForSlot(RequestItem $item, int $slotSequence, ?string $excludeAdministrationId = null): bool
    {
        return $item->medicationAdministrations()
            ->where('dose_slot_sequence', $slotSequence)
            ->where('status', MedicationAdministrationStatus::GIVEN)
            ->when($excludeAdministrationId, fn ($q) => $q->where('id', '!=', $excludeAdministrationId))
            ->exists();
    }

    /**
     * @return list<array{request_item:RequestItem,slot:DoseSlot,overdue:bool}>
     */
    public function getOverdueSlots(?Branch $branch = null): array
    {
        $graceMinutes = (int) config('clinical.mar_reminders.grace_minutes', 30);
        $now = now();
        $results = [];

        $query = RequestItem::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('prescriptionDetail', fn ($q) => $q->where('administration_context', 'in_facility'))
            ->with(['prescriptionDetail', 'serviceRequest.patient', 'serviceRequest.encounter', 'service']);

        if ($branch) {
            $query->whereHas('serviceRequest', fn ($q) => $q->where('branch_id', $branch->id));
        }

        foreach ($query->get() as $item) {
            $next = $this->getNextDueSlot($item);
            if ($next === null) {
                continue;
            }

            if ($next->dueAt->lte($now->copy()->subMinutes($graceMinutes))) {
                $results[] = [
                    'request_item' => $item,
                    'slot' => $next,
                    'overdue' => true,
                ];
            }
        }

        return $results;
    }

    /**
     * @return list<array{request_item:RequestItem,slot:DoseSlot,reminder_type:string}>
     */
    public function getDueSoonSlots(?Branch $branch = null): array
    {
        if (! config('clinical.mar_reminders.enabled', true)) {
            return [];
        }

        $leadMinutes = (int) config('clinical.mar_reminders.lead_minutes', 15);
        $graceMinutes = (int) config('clinical.mar_reminders.grace_minutes', 30);
        $now = now();
        $results = [];

        $query = RequestItem::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('prescriptionDetail', fn ($q) => $q->where('administration_context', 'in_facility'))
            ->with(['prescriptionDetail', 'serviceRequest.patient', 'serviceRequest.encounter', 'service']);

        if ($branch) {
            $query->whereHas('serviceRequest', fn ($q) => $q->where('branch_id', $branch->id));
        }

        foreach ($query->get() as $item) {
            $next = $this->getNextDueSlot($item);
            if ($next === null) {
                continue;
            }

            $frequency = $item->prescriptionDetail?->frequency;
            $isStat = in_array($frequency, ['stat', 'once'], true);

            if ($isStat && $next->dueAt->lte($now)) {
                $results[] = [
                    'request_item' => $item,
                    'slot' => $next,
                    'reminder_type' => 'due_now',
                ];

                continue;
            }

            if ($next->dueAt->between($now, $now->copy()->addMinutes($leadMinutes))) {
                $results[] = [
                    'request_item' => $item,
                    'slot' => $next,
                    'reminder_type' => 'due_soon',
                ];
            } elseif ($next->dueAt->lte($now->copy()->subMinutes($graceMinutes))) {
                $results[] = [
                    'request_item' => $item,
                    'slot' => $next,
                    'reminder_type' => 'overdue',
                ];
            }
        }

        return $results;
    }
}
