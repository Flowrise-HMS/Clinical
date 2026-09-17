<?php

declare(strict_types=1);

namespace Modules\Clinical\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Clinical\Models\DischargeSummary;

class DischargeSummaryPolicy
{
    use HandlesAuthorization;

    public function view(AuthUser $authUser, DischargeSummary $summary): bool
    {
        return $authUser->can('View Encounter') || $authUser->can('print_discharge_summary');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Update Encounter');
    }

    public function update(AuthUser $authUser, DischargeSummary $summary): bool
    {
        return $authUser->can('Update Encounter');
    }

    public function sign(AuthUser $authUser, DischargeSummary $summary): bool
    {
        return $authUser->can('sign_discharge_summary');
    }

    public function print(AuthUser $authUser, DischargeSummary $summary): bool
    {
        return $authUser->can('print_discharge_summary');
    }
}
