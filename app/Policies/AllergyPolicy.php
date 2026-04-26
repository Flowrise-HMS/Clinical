<?php

declare(strict_types=1);

namespace Modules\Clinical\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Clinical\Models\Allergy;

class AllergyPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny Allergy');
    }

    public function view(AuthUser $authUser, Allergy $allergy): bool
    {
        return $authUser->can('View Allergy');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create Allergy');
    }

    public function update(AuthUser $authUser, Allergy $allergy): bool
    {
        return $authUser->can('Update Allergy');
    }

    public function delete(AuthUser $authUser, Allergy $allergy): bool
    {
        return $authUser->can('Delete Allergy');
    }

    public function restore(AuthUser $authUser, Allergy $allergy): bool
    {
        return $authUser->can('Restore Allergy');
    }

    public function forceDelete(AuthUser $authUser, Allergy $allergy): bool
    {
        return $authUser->can('ForceDelete Allergy');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny Allergy');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny Allergy');
    }

    public function replicate(AuthUser $authUser, Allergy $allergy): bool
    {
        return $authUser->can('Replicate Allergy');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder Allergy');
    }
}
