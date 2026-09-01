<?php

namespace Modules\Clinical\Classes\Services;

use Carbon\Carbon;
use Modules\Clinical\Contracts\PrescriptionScheduleCalculatorContract;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Models\Branch;

class MedicationDoseScheduleService
{
    public function __construct(
        protected MedicationFulfillmentPolicy $policy,
        protected ?PrescriptionScheduleCalculatorContract $calculator = null,
    ) {}

    /**
     * @return list<object{sequence: int, dueAt: Carbon}>
     */
    public function getSchedule(object $detail): array
    {
        if ($this->calculator === null) {
            return [];
        }

        return $this->calculator->buildDoseSchedule($detail);
    }

    public function getNextDueSlot(RequestItem $item): ?object
    {
        if ($this->calculator === null) {
            return null;
        }

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
        if ($this->calculator === null) {
            return;
        }

        $detail = $item->prescriptionDetail;
        if (! $detail) {
            return;
        }

        $next = $this->getNextDueSlot($item);
        $detail->update(['next_dose_at' => $next?->dueAt]);
    }

    public function markSlotForAdministration(RequestItem $item, MedicationAdministration $administration): void
    {
        if ($this->calculator === null) {
            return;
        }

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

        // A slot that already holds a GIVEN dose is spoken for. Without this,
        // every dose recorded inside the first slot's grace window was stamped
        // with that same sequence — so a 12-dose QID course could end up as
        // twelve administrations all labelled slot 1, and the duplicate-slot
        // check (which reads getNextDueSlot()) never saw a conflict.
        $takenSequences = $item->medicationAdministrations()
            ->where('status', MedicationAdministrationStatus::GIVEN)
            ->whereNotNull('dose_slot_sequence')
            ->whereKeyNot($administration->getKey())
            ->pluck('dose_slot_sequence')
            ->map(fn ($sequence): int => (int) $sequence)
            ->all();

        foreach ($slots as $slot) {
            if (in_array($slot->sequence, $takenSequences, true)) {
                continue;
            }

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
     * @return list<array{request_item: RequestItem, slot: object, overdue: bool}>
     */
    public function getOverdueSlots(?Branch $branch = null): array
    {
        if ($this->calculator === null) {
            return [];
        }

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
     * @return list<array{request_item: RequestItem, slot: object, reminder_type: string}>
     */
    public function getDueSoonSlots(?Branch $branch = null): array
    {
        if ($this->calculator === null || ! config('clinical.mar_reminders.enabled', true)) {
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
