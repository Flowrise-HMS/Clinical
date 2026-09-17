<?php

namespace Modules\Clinical\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\Clinical\Events\PatientTransferred;
use Modules\Clinical\Notifications\PatientTransferredNotification;
use Modules\Clinical\Support\PatientNotificationAudience;
use Modules\Patient\Models\Patient;

class NotifyPatientOfTransfer
{
    public function handle(PatientTransferred $event): void
    {
        $encounter = $event->encounter->fresh(['patient', 'branch', 'location', 'bed']);

        if ($encounter === null || ! $encounter->patient instanceof Patient) {
            return;
        }

        Notification::send(
            PatientNotificationAudience::for($encounter->patient),
            new PatientTransferredNotification($encounter, $event->locationEvent),
        );
    }
}
