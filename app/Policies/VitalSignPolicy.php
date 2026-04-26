<?php

declare(strict_types=1);

namespace Modules\Clinical\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Clinical\Models\VitalSign;

class VitalSignPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny VitalSign');
    }

    public function view(AuthUser $authUser, VitalSign $vitalSign): bool
    {
        return $authUser->can('View VitalSign');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create VitalSign');
    }

    public function update(AuthUser $authUser, VitalSign $vitalSign): bool
    {
        return $authUser->can('Update VitalSign');
    }

    public function delete(AuthUser $authUser, VitalSign $vitalSign): bool
    {
        return $authUser->can('Delete VitalSign');
    }

    public function restore(AuthUser $authUser, VitalSign $vitalSign): bool
    {
        return $authUser->can('Restore VitalSign');
    }

    public function forceDelete(AuthUser $authUser, VitalSign $vitalSign): bool
    {
        return $authUser->can('ForceDelete VitalSign');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny VitalSign');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny VitalSign');
    }

    public function replicate(AuthUser $authUser, VitalSign $vitalSign): bool
    {
        return $authUser->can('Replicate VitalSign');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder VitalSign');
    }
}
