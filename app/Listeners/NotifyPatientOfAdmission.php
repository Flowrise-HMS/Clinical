<?php

namespace Modules\Clinical\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\Clinical\Events\PatientAdmitted;
use Modules\Clinical\Notifications\PatientAdmittedNotification;
use Modules\Clinical\Support\PatientNotificationAudience;
use Modules\Patient\Models\Patient;

class NotifyPatientOfAdmission
{
    public function handle(PatientAdmitted $event): void
    {
        $encounter = $event->encounter->fresh(['patient', 'branch', 'department', 'location', 'bed']);

        if ($encounter === null || ! $encounter->patient instanceof Patient) {
            return;
        }

        if (! empty($encounter->metadata['notified_admission_at'])) {
            return;
        }

        Notification::send(
            PatientNotificationAudience::for($encounter->patient),
            new PatientAdmittedNotification($encounter),
        );

        $encounter->forceFill(['metadata' => array_merge($encounter->metadata ?? [], [
            'notified_admission_at' => now()->toIso8601String(),
        ])])->saveQuietly();
    }
}
