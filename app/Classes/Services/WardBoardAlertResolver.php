<?php

namespace Modules\Clinical\Classes\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\ParticipantRole;
use Modules\Clinical\Models\Encounter;

/**
 * Derives the attention flags shown on a ward board bed card. Dose alerts are
 * computed from `next_dose_at` values the caller has already batched, so this
 * class never queries per encounter.
 */
class WardBoardAlertResolver
{
    /**
     * @param  list<string>  $nextDoseTimes  ISO timestamps of pending in-facility doses for this encounter
     * @return list<array{key: string, label: string, severity: string, detail: ?string}>
     */
    public function forEncounter(Encounter $encounter, CarbonInterface $now, array $nextDoseTimes = []): array
    {
        $alerts = [];

        $vitals = $encounter->patient?->latestVitals;

        if ($vitals !== null && ($vitals->isAbnormalBloodPressure() || $vitals->isLowOxygenSaturation())) {
            $parts = array_filter([
                $vitals->isAbnormalBloodPressure() && $vitals->blood_pressure ? 'BP '.$vitals->blood_pressure : null,
                $vitals->isLowOxygenSaturation() && $vitals->spo2 ? 'SpO₂ '.$vitals->spo2.'%' : null,
            ]);

            $alerts[] = $this->alert('abnormal_vitals', __('Abnormal vitals'), 'danger', implode(' · ', $parts) ?: null);
        }

        $lead = (int) config('clinical.mar_reminders.lead_minutes', 15);
        $grace = (int) config('clinical.mar_reminders.grace_minutes', 30);
        $overdue = 0;
        $due = 0;

        foreach ($nextDoseTimes as $iso) {
            $dueAt = Carbon::parse($iso);

            if ($dueAt->lte($now->copy()->subMinutes($grace))) {
                $overdue++;
            } elseif ($dueAt->lte($now->copy()->addMinutes($lead))) {
                $due++;
            }
        }

        if ($overdue > 0) {
            $alerts[] = $this->alert('dose_overdue', __('Dose overdue'), 'danger', __(':count overdue', ['count' => $overdue]));
        } elseif ($due > 0) {
            $alerts[] = $this->alert('dose_due', __('Dose due'), 'warning', __(':count due', ['count' => $due]));
        }

        if ($encounter->expected_discharge_at && $encounter->expected_discharge_at->lt($now)) {
            $alerts[] = $this->alert('past_expected_discharge', __('Past expected discharge'), 'warning', $encounter->expected_discharge_at->diffForHumans($now));
        } elseif ($encounter->isLongStay()) {
            $alerts[] = $this->alert('long_stay', __('Long stay'), 'warning', __(':days days', ['days' => $encounter->los_days]));
        }

        if ($encounter->status === EncounterStatus::ON_LEAVE) {
            $alerts[] = $this->alert('on_pass', __('On pass'), 'info', $encounter->metadata['pass']['expected_return_at'] ?? null);
        }

        $hasNurse = $encounter->relationLoaded('activeParticipants')
            ? $encounter->activeParticipants->contains(fn ($participant) => $participant->role === ParticipantRole::NURSE)
            : $encounter->activeParticipants()->where('role', ParticipantRole::NURSE)->exists();

        if (! $hasNurse) {
            $alerts[] = $this->alert('no_nurse', __('No nurse assigned'), 'gray', null);
        }

        return $alerts;
    }

    /**
     * @return array{key: string, label: string, severity: string, detail: ?string}
     */
    protected function alert(string $key, string $label, string $severity, ?string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'severity' => $severity, 'detail' => $detail];
    }
}
