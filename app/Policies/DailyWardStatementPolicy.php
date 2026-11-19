<?php

namespace Modules\Clinical\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Clinical\Models\DailyWardStatement;
use Modules\Core\Classes\Services\WardAccessService;

class DailyWardStatementPolicy
{
    use HandlesAuthorization;

    public function __construct(
        protected WardAccessService $wardAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny DailyWardStatement') || $this->wardAccess->isAnyWardIncharge($user);
    }

    public function view(User $user, DailyWardStatement $statement): bool
    {
        return $user->can('View DailyWardStatement')
            || $this->wardAccess->isWardIncharge($user, $statement->location_id);
    }

    public function create(User $user): bool
    {
        return $user->can('Create DailyWardStatement') || $this->wardAccess->isAnyWardIncharge($user);
    }

    public function update(User $user, DailyWardStatement $statement): bool
    {
        if (! $statement->status->isEditable()) {
            return false;
        }

        return $user->can('Update DailyWardStatement')
            || $this->wardAccess->isWardIncharge($user, $statement->location_id);
    }

    public function delete(User $user, DailyWardStatement $statement): bool
    {
        return $statement->status->isEditable() && $user->can('Delete DailyWardStatement');
    }
}
