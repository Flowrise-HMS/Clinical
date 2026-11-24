<?php

namespace Modules\Clinical\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Clinical\Classes\Services\TaskService;
use Modules\Clinical\Enums\TaskOutcome;
use Modules\Clinical\Models\Task;

/**
 * @group Clinical
 *
 * Fulfilment tasks created for service request items (read and workflow only).
 */
class TaskController extends ClinicalApiController
{
    protected string $branchScope = 'via:requestItem.serviceRequest';

    public function __construct(protected TaskService $tasks) {}

    public static function model(): string
    {
        return Task::class;
    }

    public function fields(): array
    {
        return ['id', 'request_item_id', 'status', 'outcome', 'performed_by', 'started_at', 'completed_at', 'duration_minutes', 'notes', 'results', 'created_at'];
    }

    public function filters(): array
    {
        return ['status' => 'status', 'request_item_id' => 'request_item_id'];
    }

    /**
     * Start a pending task.
     */
    public function start(string $id): JsonResponse
    {
        return $this->act($id, 'update', fn (Task $task) => $this->tasks->startTask($task, auth()->id()));
    }

    /**
     * Complete an in-progress task.
     *
     * @bodyParam outcome string completed or another TaskOutcome value. Example: completed
     * @bodyParam results object Structured results. Example: {"note": "done"}
     * @bodyParam notes string Notes. Example: No complications.
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'outcome' => ['nullable', Rule::enum(TaskOutcome::class)],
            'results' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->act($id, 'update', fn (Task $task) => $this->tasks->completeTask(
            $task,
            isset($data['outcome']) ? enum_from(TaskOutcome::class, $data['outcome']) : TaskOutcome::COMPLETED,
            $data['results'] ?? null,
            $data['notes'] ?? null,
        ));
    }

    /**
     * Cancel a task.
     *
     * @bodyParam reason string Why. Example: Order cancelled.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->act($id, 'update', fn (Task $task) => $this->tasks->cancelTask($task, $data['reason'] ?? null));
    }
}
