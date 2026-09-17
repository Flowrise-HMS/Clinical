<?php

namespace Modules\Clinical\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\Clinical\Events\PatientTransferred;
use Modules\Clinical\Notifications\PatientTransferredWardNotification;
use Modules\Clinical\Support\WardStaffResolver;
use Modules\Core\Models\Location;

class NotifyReceivingWardOfTransfer
{
    public function handle(PatientTransferred $event): void
    {
        $encounter = $event->encounter->fresh(['location', 'bed', 'patient']);

        if ($encounter === null || $encounter->location_id === null) {
            return;
        }

        // Moving between beds in the same ward is not a transfer the ward needs telling about.
        if ($event->locationEvent->from_location_id === $encounter->location_id) {
            return;
        }

        $ward = Location::withoutGlobalScope('branch')->find($encounter->location_id);
        $staff = WardStaffResolver::forWard($ward);

        if ($staff->isEmpty()) {
            return;
        }

        Notification::send($staff, new PatientTransferredWardNotification($encounter, $event->locationEvent));
    }
}
