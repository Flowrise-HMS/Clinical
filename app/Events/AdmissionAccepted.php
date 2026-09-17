<?php

namespace Modules\Clinical\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Clinical\Models\AdmissionRequest;
use Modules\Clinical\Models\Encounter;

/**
 * Admission-request lifecycle event, dispatched after the ADT transaction commits.
 */
class AdmissionAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(public AdmissionRequest $request, public Encounter $encounter) {}
}
