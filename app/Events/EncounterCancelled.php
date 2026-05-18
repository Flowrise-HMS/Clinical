<?php

namespace Modules\Clinical\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Clinical\Models\Encounter;

class EncounterCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(public Encounter $encounter) {}
}
