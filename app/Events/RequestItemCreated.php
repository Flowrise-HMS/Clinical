<?php

namespace Modules\Clinical\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Clinical\Models\RequestItem;

class RequestItemCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public RequestItem $requestItem) {}
}
