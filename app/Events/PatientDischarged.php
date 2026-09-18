<?php

namespace Modules\Clinical\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterLocationEvent;

/**
 * Inpatient ADT event, dispatched after the ADT transaction commits.
 */
class PatientDischarged
{
    use Dispatchable, SerializesModels;

    public function __construct(public Encounter $encounter, public EncounterLocationEvent $locationEvent, public ?Carbon $followUpAt = null, public ?string $followUpProviderId = null) {}
}
