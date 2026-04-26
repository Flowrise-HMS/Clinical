<?php

declare(strict_types=1);

namespace Modules\Clinical\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Clinical\Models\ClinicalNote;

class ClinicalNotePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny ClinicalNote');
    }

    public function view(AuthUser $authUser, ClinicalNote $clinicalNote): bool
    {
        return $authUser->can('View ClinicalNote');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create ClinicalNote');
    }

    public function update(AuthUser $authUser, ClinicalNote $clinicalNote): bool
    {
        return $authUser->can('Update ClinicalNote');
    }

    public function delete(AuthUser $authUser, ClinicalNote $clinicalNote): bool
    {
        return $authUser->can('Delete ClinicalNote');
    }

    public function restore(AuthUser $authUser, ClinicalNote $clinicalNote): bool
    {
        return $authUser->can('Restore ClinicalNote');
    }

    public function forceDelete(AuthUser $authUser, ClinicalNote $clinicalNote): bool
    {
        return $authUser->can('ForceDelete ClinicalNote');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny ClinicalNote');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny ClinicalNote');
    }

    public function replicate(AuthUser $authUser, ClinicalNote $clinicalNote): bool
    {
        return $authUser->can('Replicate ClinicalNote');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder ClinicalNote');
    }
}
