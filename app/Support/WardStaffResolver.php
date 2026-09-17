<?php

namespace Modules\Clinical\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Core\Models\Location;

/**
 * Who should hear about a ward's admissions and transfers: the nurse in
 * charge plus active users in the ward's branch holding a ward role.
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

        $roles = (array) config('clinical.wards.notify_roles', ['nurse']);

        $staff = User::query()
            ->where('is_active', true)
            ->where('branch_id', $ward->branch_id)
            ->when($roles !== [], fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('name', $roles)))
            ->get();

        $ward->loadMissing('nurseInCharge');

        if ($ward->nurseInCharge) {
            $staff->push($ward->nurseInCharge);
        }

        return $staff->unique('id')->values();
    }
}
