<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Collection;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Enums\LocationType;
use Modules\Core\Models\Location;

class BedAssignmentService
{
    public function getWardsForBranch(string $branchId): Collection
    {
        return Location::query()
            ->where('branch_id', $branchId)
            ->where('type', LocationType::ROOM)
            ->where('is_active', true)
            ->whereHas('children', fn ($q) => $q->where('type', LocationType::BED)->where('is_active', true))
            ->pluck('name', 'id');
    }

    public function getAvailableBeds(string $wardId): Collection
    {
        $occupiedBedIds = Encounter::active()
            ->whereNotNull('bed_id')
            ->pluck('bed_id');

        return Location::query()
            ->where('parent_id', $wardId)
            ->where('type', LocationType::BED)
            ->where('is_active', true)
            ->when($occupiedBedIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $occupiedBedIds))
            ->pluck('name', 'id');
    }

    public function assignBed(Encounter $encounter, string $bedId, ?int $admittedBy = null): Encounter
    {
        $occupied = Encounter::active()
            ->where('bed_id', $bedId)
            ->where('id', '!=', $encounter->id)
            ->exists();

        if ($occupied) {
            throw new \RuntimeException(__('clinical::messages.bed_already_occupied'));
        }

        if ($encounter->canTransitionTo(EncounterStatus::ARRIVED)) {
            return app(EncounterService::class)->admitPatient(
                $encounter,
                admittedBy: $admittedBy,
                bedId: $bedId
            );
        }

        $encounter->update(['bed_id' => $bedId]);
        return $encounter->fresh();
    }
}
