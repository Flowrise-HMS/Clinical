<?php

namespace Modules\Clinical\Observers;

use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Events\RequestItemCancelled;
use Modules\Clinical\Events\RequestItemCreated;
use Modules\Clinical\Events\RequestItemUpdated;
use Modules\Clinical\Models\RequestItem;

class RequestItemObserver
{
    public function created(RequestItem $requestItem): void
    {
        RequestItemCreated::dispatch($requestItem);
    }

    public function updated(RequestItem $requestItem): void
    {
        RequestItemUpdated::dispatch($requestItem);

        if ($requestItem->wasChanged('status') && $requestItem->status === RequestItemStatus::CANCELLED) {
            RequestItemCancelled::dispatch($requestItem);
        }
    }
}
