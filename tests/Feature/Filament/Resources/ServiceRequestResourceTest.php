<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Pages\CreateServiceRequest;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Pages\EditServiceRequest;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Pages\ListServiceRequests;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Pages\ViewServiceRequest;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\ServiceRequestResource;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Clinical');
    $this->migrateModules(['Core', 'Patient', 'Staff', 'Clinical']);
    $this->branch = Branch::factory()->create();
    $this->setCurrentBranch($this->branch);
    $this->patient = Patient::factory()->create(['branch_id' => $this->branch->id]);
});

FilamentResourceTestSuite::register([
    'resource' => ServiceRequestResource::class,
    'subject' => 'ServiceRequest',
    'model' => ServiceRequest::class,
    'listPage' => ListServiceRequests::class,
    'createPage' => CreateServiceRequest::class,
    'editPage' => EditServiceRequest::class,
    'viewPage' => ViewServiceRequest::class,
    'hasBulkDelete' => true,
    'hasRecordDelete' => true,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): ServiceRequest => ServiceRequest::factory()->create([
        'patient_id' => $test->patient->id,
        'branch_id' => $test->branch->id,
        ...$attributes,
    ]),
    'makeRecords' => fn (TestCase $test, int $count) => ServiceRequest::factory()->count($count)->create([
        'patient_id' => $test->patient->id,
        'branch_id' => $test->branch->id,
    ]),
    'createForm' => fn (TestCase $test): array => [
        'patient_id' => $test->patient->id,
        'status' => 'draft',
        'priority' => 'routine',
        'notes' => '<p>Factory service request.</p>',
    ],
    'updateForm' => fn (): array => [
        'notes' => '<p>Updated service request</p>',
    ],
    'schemaState' => fn (mixed $test, ServiceRequest $record): array => [
        'patient_id' => $record->patient_id,
    ],
    'requiredValidation' => [
        'priority is required' => [['priority' => null], ['priority' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'patient_id' => $payload['patient_id'],
        'status' => $payload['status'],
        'priority' => $payload['priority'],
    ],
]);
