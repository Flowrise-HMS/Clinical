<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\EncounterResource;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages\CreateEncounter;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages\EditEncounter;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages\ListEncounters;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages\ViewEncounter;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Enums\CoverageType;
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
    'resource' => EncounterResource::class,
    'subject' => 'Encounter',
    'model' => Encounter::class,
    'listPage' => ListEncounters::class,
    'createPage' => CreateEncounter::class,
    'editPage' => EditEncounter::class,
    'viewPage' => ViewEncounter::class,
    'searchColumn' => 'encounter_number',
    'sortColumn' => 'encounter_number',
    'filter' => [
        'name' => 'type',
        'value' => EncounterType::OUTPATIENT->value,
        'attribute' => 'type',
    ],
    'hasBulkDelete' => true,
    'hasRecordDelete' => false,
    'hasTableDelete' => true,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): Encounter => Encounter::factory()->create([
        'patient_id' => $test->patient->id,
        'branch_id' => $test->branch->id,
        'type' => EncounterType::OUTPATIENT,
        ...$attributes,
    ]),
    'makeRecords' => fn (TestCase $test, int $count) => Encounter::factory()->count($count)->create([
        'patient_id' => $test->patient->id,
        'branch_id' => $test->branch->id,
        'type' => EncounterType::OUTPATIENT,
    ]),
    'createForm' => fn (TestCase $test): array => [
        'patient_id' => $test->patient->id,
        'type' => EncounterType::OUTPATIENT->value,
        'priority' => EncounterPriority::ROUTINE->value,
        'status' => EncounterStatus::PLANNED->value,
        'coverage_type' => CoverageType::NONE->value,
        'branch_id' => $test->branch->id,
    ],
    'updateForm' => fn (TestCase $test): array => [
        'priority' => EncounterPriority::URGENT->value,
        'coverage_type' => CoverageType::NONE->value,
        'branch_id' => $test->branch->id,
    ],
    'schemaState' => fn (mixed $test, Encounter $record): array => [
        'type' => $record->type,
        'status' => $record->status?->value,
        'branch_id' => $record->branch_id,
    ],
    'requiredValidation' => [
        'coverage type is required' => [['coverage_type' => null], ['coverage_type' => 'required']],
        'branch is required' => [['branch_id' => null], ['branch_id' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'patient_id' => $payload['patient_id'],
        'type' => $payload['type'],
        'branch_id' => $payload['branch_id'],
    ],
]);
