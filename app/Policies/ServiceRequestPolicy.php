<?php

declare(strict_types=1);

namespace Modules\Clinical\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Clinical\Models\ServiceRequest;

class ServiceRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny ServiceRequest');
    }

    public function view(AuthUser $authUser, ServiceRequest $serviceRequest): bool
    {
        return $authUser->can('View ServiceRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create ServiceRequest');
    }

    public function update(AuthUser $authUser, ServiceRequest $serviceRequest): bool
    {
        return $authUser->can('Update ServiceRequest');
    }

    public function delete(AuthUser $authUser, ServiceRequest $serviceRequest): bool
    {
        return $authUser->can('Delete ServiceRequest');
    }

    public function restore(AuthUser $authUser, ServiceRequest $serviceRequest): bool
    {
        return $authUser->can('Restore ServiceRequest');
    }

    public function forceDelete(AuthUser $authUser, ServiceRequest $serviceRequest): bool
    {
        return $authUser->can('ForceDelete ServiceRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny ServiceRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny ServiceRequest');
    }

    public function replicate(AuthUser $authUser, ServiceRequest $serviceRequest): bool
    {
        return $authUser->can('Replicate ServiceRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder ServiceRequest');
    }
}
