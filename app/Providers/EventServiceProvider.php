<?php

namespace Modules\Clinical\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Clinical\Events\AdmissionAccepted;
use Modules\Clinical\Events\AdmissionRejected;
use Modules\Clinical\Events\AdmissionRequestCancelled;
use Modules\Clinical\Events\AdmissionRequested;
use Modules\Clinical\Events\PatientAdmitted;
use Modules\Clinical\Events\PatientDischarged;
use Modules\Clinical\Events\PatientTransferred;
use Modules\Clinical\Listeners\NotifyPatientOfAdmission;
use Modules\Clinical\Listeners\NotifyPatientOfDischarge;
use Modules\Clinical\Listeners\NotifyPatientOfTransfer;
use Modules\Clinical\Listeners\NotifyReceivingWardOfTransfer;
use Modules\Clinical\Listeners\NotifyRequesterOfAdmissionDecision;
use Modules\Clinical\Listeners\NotifyWardOfAdmissionRequest;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application. Event discovery only
     * scans the application's own Listeners directory, so module listeners
     * are registered explicitly here.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        PatientAdmitted::class => [NotifyPatientOfAdmission::class],
        PatientTransferred::class => [NotifyPatientOfTransfer::class, NotifyReceivingWardOfTransfer::class],
        PatientDischarged::class => [NotifyPatientOfDischarge::class],
        AdmissionRequested::class => [NotifyWardOfAdmissionRequest::class],
        AdmissionAccepted::class => [NotifyRequesterOfAdmissionDecision::class],
        AdmissionRejected::class => [NotifyRequesterOfAdmissionDecision::class],
        AdmissionRequestCancelled::class => [NotifyRequesterOfAdmissionDecision::class],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
