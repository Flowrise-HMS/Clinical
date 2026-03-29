<?php

namespace Modules\Clinical\Contracts;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Modules\Clinical\Models\Task;

interface TaskableContract
{
    public function getTask(): ?Task;

    public function getTaskId(): ?string;

    public function canBeFulfilledBy(User $user): bool;

    public function getFulfillingRoles(): Collection;

    public function getPrerequisites(): array;

    public function getWarnings(): array;
}
