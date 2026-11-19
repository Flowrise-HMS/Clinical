<?php

namespace Modules\Clinical\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Clinical\Models\WardInchargeNote;
use Modules\Core\Classes\Services\WardAccessService;

class WardInchargeNotePolicy
{
    use HandlesAuthorization;

    public function __construct(
        protected WardAccessService $wardAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny WardInchargeNote') || $this->wardAccess->isAnyWardIncharge($user);
    }

    public function view(User $user, WardInchargeNote $note): bool
    {
        return $user->can('View WardInchargeNote')
            || $this->wardAccess->isWardIncharge($user, $note->location_id);
    }

    public function create(User $user): bool
    {
        return $user->can('Create WardInchargeNote') || $this->wardAccess->isAnyWardIncharge($user);
    }

    public function update(User $user, WardInchargeNote $note): bool
    {
        return $user->can('Update WardInchargeNote')
            || $note->authored_by === $user->id
            || $this->wardAccess->isWardIncharge($user, $note->location_id);
    }

    public function delete(User $user, WardInchargeNote $note): bool
    {
        return $user->can('Delete WardInchargeNote')
            || $this->wardAccess->isWardIncharge($user, $note->location_id);
    }
}
