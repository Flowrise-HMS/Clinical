<?php

namespace Modules\Clinical\Contracts;

use Illuminate\Foundation\Auth\User;
use Modules\Clinical\Enums\TaskOutcome;
use Modules\Clinical\Models\Task;

interface TaskProcessorContract
{
    public function beforeCreate(Task $task): Task;

    public function afterCreate(Task $task): void;

    public function beforeStart(Task $task): bool;

    public function afterStart(Task $task): void;

    public function beforeComplete(Task $task, TaskOutcome $outcome): bool;

    public function afterComplete(Task $task): void;

    public function beforeCancel(Task $task): bool;

    public function afterCancel(Task $task): void;

    public function validateFulfillment(User $user, Task $task): bool;

    public function getValidationErrors(User $user, Task $task): array;
}
