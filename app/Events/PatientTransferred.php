<?php

namespace Modules\Clinical\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterLocationEvent;

/**
 * Inpatient ADT event, dispatched after the ADT transaction commits.
 */
class PatientTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(public Encounter $encounter, public EncounterLocationEvent $locationEvent) {}
}
