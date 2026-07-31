<?php

declare(strict_types=1);

namespace Modules\Clinical\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Clinical\Models\CarePlan;

class CarePlanPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny CarePlan');
    }

    public function view(AuthUser $authUser, CarePlan $carePlan): bool
    {
        return $authUser->can('View CarePlan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create CarePlan');
    }

    public function update(AuthUser $authUser, CarePlan $carePlan): bool
    {
        return $authUser->can('Update CarePlan');
    }

    public function delete(AuthUser $authUser, CarePlan $carePlan): bool
    {
        return $authUser->can('Delete CarePlan');
    }

    public function restore(AuthUser $authUser, CarePlan $carePlan): bool
    {
        return $authUser->can('Restore CarePlan');
    }

    public function forceDelete(AuthUser $authUser, CarePlan $carePlan): bool
    {
        return $authUser->can('ForceDelete CarePlan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny CarePlan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny CarePlan');
    }

    public function replicate(AuthUser $authUser, CarePlan $carePlan): bool
    {
        return $authUser->can('Replicate CarePlan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder CarePlan');
    }

    public function evaluate(AuthUser $authUser, CarePlan $carePlan): bool
    {
        return $authUser->can('Evaluate CarePlan');
    }
}
