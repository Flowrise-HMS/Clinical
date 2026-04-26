<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;
use Modules\Clinical\Enums\TaskStatus;
use Modules\Clinical\Models\Task;

class MyTasksWidget extends Widget
{
    protected string $view = 'clinical::widgets.my-tasks-widget';

    protected static bool $isDiscovered = false;

    protected int $sorting = 2;

    public Collection $tasks;

    public function mount(): void
    {
        $this->loadTasks();
    }

    public function refreshTasks(): void
    {
        $this->loadTasks();
    }

    protected function loadTasks(): void
    {
        $workspaceService = app(ClinicalWorkspaceService::class);
        $this->tasks = $workspaceService->getMyPendingTasks();
    }

    public function completeTask(int $taskId): void
    {
        $task = Task::find($taskId);
        if ($task) {
            $task->update(['status' => TaskStatus::COMPLETED]);
            $this->loadTasks();
        }
    }
}
