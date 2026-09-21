<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Pages\CreateVitalSign;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Pages\EditVitalSign;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Pages\ListVitalSigns;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Pages\ViewVitalSign;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\VitalSignResource;
use Modules\Clinical\Models\VitalSign;
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
    'resource' => VitalSignResource::class,
    'subject' => 'VitalSign',
    'model' => VitalSign::class,
    'listPage' => ListVitalSigns::class,
    'createPage' => CreateVitalSign::class,
    'editPage' => EditVitalSign::class,
    'viewPage' => ViewVitalSign::class,
    'hasBulkDelete' => true,
    'hasRecordDelete' => true,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): VitalSign => VitalSign::factory()->create([
        'patient_id' => $test->patient->id,
        'branch_id' => $test->branch->id,
        ...$attributes,
    ]),
    'makeRecords' => fn (TestCase $test, int $count) => VitalSign::factory()->count($count)->create([
        'patient_id' => $test->patient->id,
        'branch_id' => $test->branch->id,
    ]),
    'createForm' => fn (TestCase $test): array => [
        'patient_id' => $test->patient->id,
        'systolic_bp' => 118,
        'diastolic_bp' => 76,
        'heart_rate' => 72,
        'temperature' => 36.8,
    ],
    'updateForm' => fn (): array => [
        'systolic_bp' => 122,
        'diastolic_bp' => 80,
        'heart_rate' => 78,
    ],
    'schemaState' => fn (mixed $test, VitalSign $record): array => [
        'systolic_bp' => $record->systolic_bp,
        'diastolic_bp' => $record->diastolic_bp,
    ],
    'requiredValidation' => [
        'systolic is required' => [['systolic_bp' => null], ['systolic_bp' => 'required']],
        'diastolic is required' => [['diastolic_bp' => null], ['diastolic_bp' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'patient_id' => $payload['patient_id'],
        'systolic_bp' => $payload['systolic_bp'],
        'diastolic_bp' => $payload['diastolic_bp'],
    ],
]);
