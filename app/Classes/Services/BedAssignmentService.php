<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Collection;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Enums\BedStatus;
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

    /**
     * Beds a patient could be placed in right now: active, not held by an
     * active encounter, and either available or reserved for this admission.
     */
    public function getAvailableBeds(string $wardId, ?string $forEncounterId = null, ?string $forRequestId = null): Collection
    {
        $occupiedBedIds = Encounter::active()
            ->whereNotNull('bed_id')
            ->when($forEncounterId, fn ($q) => $q->where('id', '!=', $forEncounterId))
            ->pluck('bed_id');

        $references = array_values(array_filter([$forEncounterId, $forRequestId]));

        return Location::query()
            ->where('parent_id', $wardId)
            ->where('type', LocationType::BED)
            ->where('is_active', true)
            ->when($occupiedBedIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $occupiedBedIds))
            ->where(function ($query) use ($references): void {
                $query->whereNull('status')
                    ->orWhere('status', BedStatus::AVAILABLE->value);

                if ($references !== []) {
                    $query->orWhere(fn ($reserved) => $reserved
                        ->whereIn('status', [BedStatus::RESERVED->value, BedStatus::OCCUPIED->value])
                        ->whereIn('status_reference', $references));
                }
            })
            ->pluck('name', 'id');
    }

    /**
     * The single place that decides whether a bed can take a patient.
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function assertAssignable(
        string $bedId,
        ?string $branchId = null,
        ?string $forEncounterId = null,
        ?string $forRequestId = null,
        ?string $patientGender = null,
    ): Location {
        // Beds are looked up outside the branch scope so a user acting for
        // another branch still resolves the bed; branch is checked explicitly.
        $bed = Location::withoutGlobalScope('branch')->with('parent')->find($bedId);

        if ($bed === null || $bed->type !== LocationType::BED) {
            throw new \InvalidArgumentException(__('The selected location is not a bed.'));
        }

        if (! $bed->is_active) {
            throw new \InvalidArgumentException(__('Bed :name is inactive.', ['name' => $bed->name]));
        }

        if ($branchId !== null && (string) $bed->branch_id !== (string) $branchId) {
            throw new \InvalidArgumentException(__('Bed :name belongs to another branch.', ['name' => $bed->name]));
        }

        if ($bed->parent && ! $bed->parent->genderPolicy()->admits($patientGender)) {
            throw new \InvalidArgumentException(__(':ward admits :policy patients only.', [
                'ward' => $bed->parent->name,
                'policy' => strtolower((string) $bed->parent->genderPolicy()->getLabel()),
            ]));
        }

        $occupied = Encounter::active()
            ->where('bed_id', $bedId)
            ->when($forEncounterId, fn ($q) => $q->where('id', '!=', $forEncounterId))
            ->exists();

        if ($occupied) {
            throw new \RuntimeException(__('clinical::messages.bed_already_occupied'));
        }

        $status = $bed->bedStatus();
        $heldFor = $bed->status_reference;
        $mine = in_array($heldFor, array_filter([$forEncounterId, $forRequestId]), true);

        $allowed = match ($status) {
            BedStatus::AVAILABLE => true,
            BedStatus::RESERVED, BedStatus::OCCUPIED => $heldFor === null || $mine,
            default => false,
        };

        if (! $allowed) {
            throw new \InvalidArgumentException(__('Bed :name is :status and cannot be assigned.', [
                'name' => $bed->name,
                'status' => strtolower((string) $status->getLabel()),
            ]));
        }

        return $bed;
    }

    /**
     * Records the bed on the encounter. Status transitions and occupancy are
     * the caller's (AdtService) responsibility.
     */
    public function assignBed(Encounter $encounter, string $bedId, ?int $admittedBy = null): Encounter
    {
        $this->assertAssignable(
            $bedId,
            $encounter->branch_id,
            $encounter->id,
            null,
            $this->patientGender($encounter),
        );

        $encounter->update(['bed_id' => $bedId]);

        return $encounter->fresh();
    }

    public function patientGender(Encounter $encounter): ?string
    {
        $gender = $encounter->patient?->gender;

        if ($gender === null) {
            return null;
        }

        return is_object($gender) && isset($gender->value) ? (string) $gender->value : (string) $gender;
    }
}
