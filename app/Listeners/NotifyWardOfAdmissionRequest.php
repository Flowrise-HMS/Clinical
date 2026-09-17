<?php

namespace Modules\Clinical\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\Clinical\Events\AdmissionRequested;
use Modules\Clinical\Notifications\AdmissionRequestedNotification;
use Modules\Clinical\Support\WardStaffResolver;

class NotifyWardOfAdmissionRequest
{
    public function handle(AdmissionRequested $event): void
    {
        $request = $event->request->fresh(['requestedWard']);

        if ($request === null) {
            return;
        }

        $staff = WardStaffResolver::forWard($request->requestedWard);

        if ($staff->isEmpty()) {
            return;
        }

        Notification::send($staff, new AdmissionRequestedNotification($request));
    }
}
