<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Enums\TaskStatus;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Pages\CreateTask;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Pages\EditTask;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Pages\ListTasks;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Pages\ViewTask;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\TaskResource;
use Modules\Clinical\Models\Task;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Clinical');
    $this->migrateModules(['Core', 'Patient', 'Staff', 'Clinical']);
});

FilamentResourceTestSuite::register([
    'resource' => TaskResource::class,
    'subject' => 'Task',
    'model' => Task::class,
    'listPage' => ListTasks::class,
    'createPage' => CreateTask::class,
    'editPage' => EditTask::class,
    'viewPage' => ViewTask::class,
    'filter' => [
        'name' => 'status',
        'value' => TaskStatus::PENDING->value,
        'attribute' => 'status',
    ],
    'hasBulkDelete' => true,
    'hasRecordDelete' => true,
    'makeRecords' => fn (TestCase $test, int $count) => Task::factory()->count($count)->create([
        'status' => TaskStatus::PENDING,
    ]),
    // Create form has no request_item_id field; factory-backed records cover list/edit/delete.
    'updateForm' => fn (): array => [
        'status' => TaskStatus::IN_PROGRESS->value,
        'notes' => 'Updated task notes',
    ],
    'schemaState' => fn (mixed $test, Task $record): array => [
        'status' => $record->status,
    ],
]);
