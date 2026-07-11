<?php

namespace Modules\Clinical\Classes\Support;

use Illuminate\Support\Collection;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Contracts\PatientFinancialHoldChecker;

class RequestItemTableEnricher
{
    /**
     * @param  Collection<int, RequestItem>  $items
     */
    public static function applyFinancialHolds(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $flags = app(PatientFinancialHoldChecker::class)
            ->resolveFinancialHoldsForRequestItems($items);

        foreach ($items as $item) {
            if (array_key_exists((string) $item->id, $flags)) {
                $item->financialHoldResolved = $flags[(string) $item->id];
            }
        }
    }
}
