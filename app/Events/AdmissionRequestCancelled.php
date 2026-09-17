<?php

namespace Modules\Clinical\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Clinical\Models\AdmissionRequest;

/**
 * Admission-request lifecycle event, dispatched after the ADT transaction commits.
 */
class AdmissionRequestCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(public AdmissionRequest $request, public bool $expired = false) {}
}
