<?php

declare(strict_types=1);

namespace Modules\Clinical\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Clinical\Models\Encounter;

class EncounterPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny Encounter');
    }

    public function view(AuthUser $authUser, Encounter $encounter): bool
    {
        return $authUser->can('View Encounter');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create Encounter');
    }

    public function update(AuthUser $authUser, Encounter $encounter): bool
    {
        return $authUser->can('Update Encounter');
    }

    public function delete(AuthUser $authUser, Encounter $encounter): bool
    {
        return $authUser->can('Delete Encounter');
    }

    public function restore(AuthUser $authUser, Encounter $encounter): bool
    {
        return $authUser->can('Restore Encounter');
    }

    public function forceDelete(AuthUser $authUser, Encounter $encounter): bool
    {
        return $authUser->can('ForceDelete Encounter');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny Encounter');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny Encounter');
    }

    public function replicate(AuthUser $authUser, Encounter $encounter): bool
    {
        return $authUser->can('Replicate Encounter');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder Encounter');
    }
}
