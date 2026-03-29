<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Modules\Clinical\Contracts\TaskProcessorContract;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Enums\RequestStatus;
use Modules\Clinical\Enums\TaskOutcome;
use Modules\Clinical\Enums\TaskStatus;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\Task;

class TaskService
{
    protected array $processors = [];

    public function registerProcessor(TaskProcessorContract $processor): void
    {
        $this->processors[] = $processor;
    }

    public function createTask(
        RequestItem $item,
        ?int $createdBy = null
    ): Task {
        foreach ($this->processors as $processor) {
            $processor->beforeCreate($item);
        }

        $task = $item->tasks()->create([
            'status' => TaskStatus::PENDING,
            'metadata' => ['created_by' => $createdBy ?? auth()->id()],
        ]);

        foreach ($this->processors as $processor) {
            $processor->afterCreate($task);
        }

        return $task;
    }

    public function startTask(
        Task $task,
        ?int $performedBy = null
    ): Task {
        if (! $task->isPending()) {
            throw new \InvalidArgumentException('Task is not in pending status');
        }

        $userId = $performedBy ?? auth()->id();

        foreach ($this->processors as $processor) {
            if (! $processor->beforeStart($task)) {
                throw new \InvalidArgumentException('Task cannot be started: processor validation failed');
            }
        }

        $task->update([
            'status' => TaskStatus::IN_PROGRESS,
            'performed_by' => $userId,
            'started_at' => now(),
        ]);

        $item = $task->requestItem;
        if ($item && $item->status === RequestItemStatus::PENDING) {
            $item->update(['status' => RequestItemStatus::IN_PROGRESS]);
        }

        foreach ($this->processors as $processor) {
            $processor->afterStart($task);
        }

        return $task->fresh();
    }

    public function completeTask(
        Task $task,
        TaskOutcome $outcome = TaskOutcome::COMPLETED,
        ?array $results = null,
        ?string $notes = null
    ): Task {
        if (! $task->isInProgress()) {
            throw new \InvalidArgumentException('Task is not in progress');
        }

        foreach ($this->processors as $processor) {
            if (! $processor->beforeComplete($task, $outcome)) {
                throw new \InvalidArgumentException('Task cannot be completed: processor validation failed');
            }
        }

        $startedAt = $task->started_at ?? now();

        $task->update([
            'status' => TaskStatus::COMPLETED,
            'outcome' => $outcome,
            'completed_at' => now(),
            'duration_minutes' => $startedAt->diffInMinutes(now()),
            'results' => $results ?? $task->results,
            'notes' => $notes ?? $task->notes,
        ]);

        $item = $task->requestItem;
        if ($item && ! $item->isTerminal()) {
            $item->update([
                'status' => RequestItemStatus::COMPLETED,
                'fulfilled_by' => $task->performed_by,
                'fulfilled_at' => now(),
            ]);

            $request = $item->serviceRequest;
            $hasPendingItems = $request->items()
                ->whereNotIn('status', [
                    RequestItemStatus::COMPLETED->value,
                    RequestItemStatus::CANCELLED->value,
                ])
                ->doesntExist();

            if ($hasPendingItems) {
                $request->update(['status' => RequestStatus::COMPLETED]);
            }
        }

        foreach ($this->processors as $processor) {
            $processor->afterComplete($task);
        }

        return $task->fresh();
    }

    public function cancelTask(Task $task, ?string $reason = null): Task
    {
        if ($task->isTerminal()) {
            throw new \InvalidArgumentException('Task is already in terminal state');
        }

        foreach ($this->processors as $processor) {
            if (! $processor->beforeCancel($task)) {
                throw new \InvalidArgumentException('Task cannot be cancelled: processor validation failed');
            }
        }

        $task->update([
            'status' => TaskStatus::CANCELLED,
            'outcome' => TaskOutcome::CANCELLED,
            'notes' => $reason ? ($task->notes ? "{$task->notes}\n{$reason}" : $reason) : $task->notes,
        ]);

        $item = $task->requestItem;
        if ($item && $item->status !== RequestItemStatus::COMPLETED) {
            $hasActiveTasks = $item->tasks()
                ->where('id', '!=', $task->id)
                ->whereNotIn('status', [TaskStatus::CANCELLED->value])
                ->exists();

            if (! $hasActiveTasks && $item->status === RequestItemStatus::IN_PROGRESS) {
                $hasCompletedTasks = $item->tasks()
                    ->where('id', '!=', $task->id)
                    ->where('status', TaskStatus::COMPLETED->value)
                    ->exists();

                if ($hasCompletedTasks) {
                    $item->update(['status' => RequestItemStatus::COMPLETED]);
                } else {
                    $item->update(['status' => RequestItemStatus::PENDING]);
                }
            }
        }

        foreach ($this->processors as $processor) {
            $processor->afterCancel($task);
        }

        return $task->fresh();
    }

    public function canUserFulfillTask(User $user, Task $task): bool
    {
        $item = $task->requestItem;
        $service = $item?->service;

        if (! $service) {
            return false;
        }

        $allowedRoles = $service->roles;

        if ($allowedRoles->isEmpty()) {
            return true;
        }

        return $user->hasAnyRole($allowedRoles->pluck('name')->toArray());
    }

    public function getValidationErrors(User $user, Task $task): array
    {
        $errors = [];

        foreach ($this->processors as $processor) {
            $processorErrors = $processor->getValidationErrors($user, $task);
            $errors = array_merge($errors, $processorErrors);
        }

        return $errors;
    }

    public function getPendingTasksByRole(string $role): Collection
    {
        return Task::pending()
            ->whereHas('requestItem.service.roles', fn ($q) => $q->where('name', $role))
            ->with(['requestItem.service', 'requestItem.serviceRequest.patient'])
            ->orderBy('created_at')
            ->get();
    }

    public function getTasksByUser(int $userId): Collection
    {
        return Task::where('performed_by', $userId)
            ->with(['requestItem.service', 'requestItem.serviceRequest.patient'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function getActiveTasks(): Collection
    {
        return Task::whereIn('status', [TaskStatus::PENDING, TaskStatus::IN_PROGRESS])
            ->with(['requestItem.service', 'requestItem.serviceRequest.patient', 'performedBy'])
            ->orderBy('created_at')
            ->get();
    }

    public function addResult(Task $task, string $key, mixed $value): Task
    {
        $results = $task->results ?? [];
        $results[$key] = $value;

        $task->update(['results' => $results]);

        return $task->fresh();
    }

    public function getTaskStatistics(): array
    {
        return [
            'pending' => Task::pending()->count(),
            'in_progress' => Task::where('status', TaskStatus::IN_PROGRESS)->count(),
            'completed_today' => Task::completed()
                ->whereDate('completed_at', today())
                ->count(),
            'cancelled_today' => Task::cancelled()
                ->whereDate('updated_at', today())
                ->count(),
        ];
    }
}
