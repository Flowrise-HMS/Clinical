<?php

namespace Modules\Clinical\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\Clinical\Events\PatientDischarged;
use Modules\Clinical\Notifications\PatientDischargedNotification;
use Modules\Clinical\Support\PatientNotificationAudience;
use Modules\Patient\Models\Patient;

class NotifyPatientOfDischarge
{
    public function handle(PatientDischarged $event): void
    {
        $encounter = $event->encounter->fresh(['patient', 'branch']);

        if ($encounter === null || ! $encounter->patient instanceof Patient) {
            return;
        }

        Notification::send(
            PatientNotificationAudience::for($encounter->patient),
            new PatientDischargedNotification($encounter),
        );
    }
}
