<?php

namespace Modules\Clinical\Classes\Services;

use Carbon\CarbonInterface;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Enums\MedicationSlotStatus;
use Modules\Clinical\Models\MedicationAdministration;

/**
 * Classifies a scheduled dose slot for display. Uses the same lead and grace
 * windows as the MAR reminders so the canvas agrees with notifications.
 */
class MedicationSlotStatusResolver
{
    public function resolve(
        CarbonInterface $dueAt,
        ?MedicationAdministration $administration,
        bool $isStat,
        CarbonInterface $now,
    ): MedicationSlotStatus {
        if ($administration !== null) {
            return match ($administration->status) {
                MedicationAdministrationStatus::GIVEN => MedicationSlotStatus::GIVEN,
                MedicationAdministrationStatus::OMITTED => MedicationSlotStatus::OMITTED,
                MedicationAdministrationStatus::REFUSED => MedicationSlotStatus::REFUSED,
                default => MedicationSlotStatus::GIVEN,
            };
        }

        $leadMinutes = $this->leadMinutes();
        $graceMinutes = $this->graceMinutes();

        // STAT/once doses never age into "overdue": they are due the moment
        // they are ordered, matching MedicationDoseScheduleService::getDueSoonSlots().
        if ($isStat && $dueAt->lte($now)) {
            return MedicationSlotStatus::DUE_NOW;
        }

        if ($dueAt->lte($now->copy()->subMinutes($graceMinutes))) {
            return MedicationSlotStatus::OVERDUE;
        }

        if ($dueAt->lte($now)) {
            return MedicationSlotStatus::DUE_NOW;
        }

        if ($dueAt->lte($now->copy()->addMinutes($leadMinutes))) {
            return MedicationSlotStatus::DUE_SOON;
        }

        return MedicationSlotStatus::UPCOMING;
    }

    public function leadMinutes(): int
    {
        return (int) config('clinical.mar_reminders.lead_minutes', 15);
    }

    public function graceMinutes(): int
    {
        return (int) config('clinical.mar_reminders.grace_minutes', 30);
    }
}
