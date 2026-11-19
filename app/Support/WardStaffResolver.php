<?php

namespace Modules\Clinical\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Core\Models\Location;

/**
 * Who should hear about a ward's admissions, transfers, and operational alerts:
 * - Always the primary nurse in charge + any active staff explicitly assigned
 *   to the ward (`location_staff`, including deputy incharges and ward nurses).
 * - If no ward-specific staff are assigned yet, falls back to active branch
 *   users holding a ward role so existing setups continue receiving alerts.
 */
class WardStaffResolver
{
    /**
     * @return Collection<int, User>
     */
    public static function forWard(?Location $ward): Collection
    {
        if ($ward === null) {
            return collect();
        }

        $ward->loadMissing([
            'nurseInCharge',
            'wardStaff' => fn ($q) => $q->where('is_active', true)->with('user'),
        ]);

        $assignedUsers = $ward->wardStaff
            ->pluck('user')
            ->filter(fn (?User $u): bool => $u !== null && $u->is_active)
            ->values();

        if ($assignedUsers->isNotEmpty()) {
            if ($ward->nurseInCharge && $ward->nurseInCharge->is_active) {
                $assignedUsers->push($ward->nurseInCharge);
            }

            return $assignedUsers->unique('id')->values();
        }

        $roles = (array) config('clinical.wards.notify_roles', ['nurse', 'ward_incharge']);

        $staff = User::query()
            ->where('is_active', true)
            ->where('branch_id', $ward->branch_id)
            ->when($roles !== [], fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('name', $roles)))
            ->get();

        if ($ward->nurseInCharge && $ward->nurseInCharge->is_active) {
            $staff->push($ward->nurseInCharge);
        }

        return $staff->unique('id')->values();
    }
}
