<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\TaskOutcome;
use Modules\Clinical\Enums\TaskStatus;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\Task;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'request_item_id' => RequestItem::factory(),
            'status' => TaskStatus::PENDING,
            'outcome' => null,
            'performed_by' => null,
            'started_at' => null,
            'completed_at' => null,
            'duration_minutes' => null,
            'notes' => null,
            'results' => null,
            'metadata' => null,
        ];
    }

    public function forItem(RequestItem $item): static
    {
        return $this->state(fn (array $attributes) => [
            'request_item_id' => $item->id,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::PENDING,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::IN_PROGRESS,
            'performed_by' => User::factory()->create()->id,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::COMPLETED,
            'outcome' => TaskOutcome::COMPLETED,
            'performed_by' => User::factory()->create()->id,
            'started_at' => now()->subMinutes(30),
            'completed_at' => now(),
            'duration_minutes' => 30,
        ]);
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::COMPLETED,
            'outcome' => TaskOutcome::PARTIAL,
            'performed_by' => User::factory()->create()->id,
            'started_at' => now()->subMinutes(15),
            'completed_at' => now(),
            'duration_minutes' => 15,
            'notes' => 'Partially completed due to time constraints',
        ]);
    }

    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::COMPLETED,
            'outcome' => TaskOutcome::NO_SHOW,
            'notes' => 'Patient did not show up',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::CANCELLED,
            'outcome' => TaskOutcome::CANCELLED,
            'notes' => 'Task cancelled',
        ]);
    }
}
