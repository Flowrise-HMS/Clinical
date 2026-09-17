<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Facades\DB;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Enums\BedStatus;
use Modules\Core\Enums\LocationType;
use Modules\Core\Models\Location;

/**
 * Seeds bed statuses from the occupancy that already exists in encounters.
 * Safe to run repeatedly: it only touches beds whose status disagrees with
 * the active encounter holding them, or beds that have no status yet.
 */
class BedStatusBackfillService
{
    /**
     * @return array{occupied: int, available: int}
     */
    public function run(): array
    {
        return DB::transaction(function (): array {
            $held = Encounter::query()
                ->active()
                ->whereNotNull('bed_id')
                ->pluck('id', 'bed_id');

            $occupied = 0;

            foreach ($held as $bedId => $encounterId) {
                $occupied += Location::query()
                    ->whereKey($bedId)
                    ->where('type', LocationType::BED->value)
                    ->where(fn ($query) => $query
                        ->whereNull('status')
                        ->orWhere('status', '!=', BedStatus::OCCUPIED->value)
                        ->orWhere('status_reference', '!=', $encounterId)
                        ->orWhereNull('status_reference'))
                    ->update([
                        'status' => BedStatus::OCCUPIED->value,
                        'status_reference' => $encounterId,
                        'status_reason' => null,
                        'status_changed_at' => now(),
                    ]);
            }

            $available = Location::query()
                ->where('type', LocationType::BED->value)
                ->whereNull('status')
                ->whereNotIn('id', $held->keys()->all())
                ->update([
                    'status' => BedStatus::AVAILABLE->value,
                    'status_changed_at' => now(),
                ]);

            return ['occupied' => $occupied, 'available' => $available];
        });
    }
}
