<?php

namespace Modules\Clinical\Observers;

use Illuminate\Support\Facades\Notification;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Events\EncounterFinished;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Notifications\VisitCompletedNotification;
use Modules\Clinical\Notifications\VisitStartedNotification;
use Modules\Clinical\Support\PatientNotificationAudience;
use Modules\Patient\Models\Patient;

/**
 * Outpatient visit notifications and the EncounterFinished event. Inpatient
 * admission/transfer/discharge messages are driven by AdtService events, so
 * an admitted patient is never told their "visit" started or ended.
 */
class EncounterObserver
{
    public function created(Encounter $encounter): void
    {
        $this->maybeNotifyVisitStarted($encounter);
    }

    public function updated(Encounter $encounter): void
    {
        $this->maybeNotifyVisitStarted($encounter);

        if ($encounter->wasChanged('status') && $encounter->status === EncounterStatus::FINISHED) {
            if (! $encounter->isInpatient()) {
                $this->notifyVisitCompleted($encounter);
            }

            EncounterFinished::dispatch($encounter->fresh());
        }
    }

    protected function maybeNotifyVisitStarted(Encounter $encounter): void
    {
        if ($encounter->isInpatient()) {
            return;
        }

        if (! in_array($encounter->status, [EncounterStatus::ARRIVED, EncounterStatus::IN_PROGRESS], true)) {
            return;
        }

        $meta = $encounter->metadata ?? [];
        if (! empty($meta['notified_visit_started_at']) || ! empty($meta['notified_admitted'])) {
            return;
        }

        $patient = $this->patientOf($encounter);
        if ($patient === null) {
            return;
        }

        Notification::send(
            PatientNotificationAudience::for($patient),
            new VisitStartedNotification($encounter->fresh(['patient', 'branch', 'department']))
        );

        $encounter->forceFill(['metadata' => array_merge($encounter->metadata ?? [], [
            'notified_visit_started_at' => now()->toIso8601String(),
        ])])->saveQuietly();
    }

    protected function notifyVisitCompleted(Encounter $encounter): void
    {
        $patient = $this->patientOf($encounter);
        if ($patient === null) {
            return;
        }

        Notification::send(
            PatientNotificationAudience::for($patient),
            new VisitCompletedNotification($encounter->fresh(['patient', 'branch']))
        );
    }

    protected function patientOf(Encounter $encounter): ?Patient
    {
        if ($encounter->patient_id === null) {
            return null;
        }

        $encounter->loadMissing('patient');

        return $encounter->patient instanceof Patient ? $encounter->patient : null;
    }
}
