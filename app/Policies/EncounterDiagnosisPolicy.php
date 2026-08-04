<?php

declare(strict_types=1);

namespace Modules\Clinical\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Clinical\Models\EncounterDiagnosis;

class EncounterDiagnosisPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny EncounterDiagnosis');
    }

    public function view(AuthUser $authUser, EncounterDiagnosis $encounterDiagnosis): bool
    {
        return $authUser->can('View EncounterDiagnosis');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create EncounterDiagnosis');
    }

    public function update(AuthUser $authUser, EncounterDiagnosis $encounterDiagnosis): bool
    {
        return $authUser->can('Update EncounterDiagnosis');
    }

    public function delete(AuthUser $authUser, EncounterDiagnosis $encounterDiagnosis): bool
    {
        return $authUser->can('Delete EncounterDiagnosis');
    }

    public function restore(AuthUser $authUser, EncounterDiagnosis $encounterDiagnosis): bool
    {
        return $authUser->can('Restore EncounterDiagnosis');
    }

    public function forceDelete(AuthUser $authUser, EncounterDiagnosis $encounterDiagnosis): bool
    {
        return $authUser->can('ForceDelete EncounterDiagnosis');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny EncounterDiagnosis');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny EncounterDiagnosis');
    }

    public function replicate(AuthUser $authUser, EncounterDiagnosis $encounterDiagnosis): bool
    {
        return $authUser->can('Replicate EncounterDiagnosis');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder EncounterDiagnosis');
    }
}
