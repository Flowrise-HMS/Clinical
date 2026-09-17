<?php

namespace Modules\Clinical\Listeners;

use App\Models\User;
use Modules\Clinical\Events\AdmissionAccepted;
use Modules\Clinical\Events\AdmissionRejected;
use Modules\Clinical\Events\AdmissionRequestCancelled;
use Modules\Clinical\Notifications\AdmissionDecidedNotification;

class NotifyRequesterOfAdmissionDecision
{
    public function handle(AdmissionAccepted|AdmissionRejected|AdmissionRequestCancelled $event): void
    {
        // A requester withdrawing their own request needs no notification.
        if ($event instanceof AdmissionRequestCancelled && ! $event->expired) {
            return;
        }

        $request = $event->request->fresh(['requester']);

        if ($request === null || ! $request->requester instanceof User) {
            return;
        }

        $request->requester->notify(new AdmissionDecidedNotification($request));
    }
}
