<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Enums\DiagnosisType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\EncounterDiagnosisResource;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Pages\CreateEncounterDiagnosis;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Pages\EditEncounterDiagnosis;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Pages\ListEncounterDiagnoses;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
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
    $this->encounter = Encounter::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
    ]);
});

FilamentResourceTestSuite::register([
    'resource' => EncounterDiagnosisResource::class,
    'subject' => 'EncounterDiagnosis',
    'model' => EncounterDiagnosis::class,
    'listPage' => ListEncounterDiagnoses::class,
    'createPage' => CreateEncounterDiagnosis::class,
    'editPage' => EditEncounterDiagnosis::class,
    'searchColumn' => 'icd_code',
    'sortColumn' => 'icd_code',
    'filter' => [
        'name' => 'type',
        'value' => DiagnosisType::Primary->value,
        'attribute' => 'type',
    ],
    'hasBulkDelete' => true,
    'hasRecordDelete' => false,
    'hasTableDelete' => false,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): EncounterDiagnosis => EncounterDiagnosis::factory()->create([
        'encounter_id' => $test->encounter->id,
        'icd_code' => 'A09',
        'type' => DiagnosisType::Primary,
        ...$attributes,
    ]),
    'makeRecords' => function (TestCase $test, int $count) {
        $types = DiagnosisType::cases();
        $records = collect();

        for ($index = 0; $index < $count; $index++) {
            $records->push(EncounterDiagnosis::factory()->create([
                'encounter_id' => $test->encounter->id,
                'icd_code' => 'Z'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'description' => 'Diagnosis '.$index,
                'type' => $index === 0 ? DiagnosisType::Primary : $types[$index % count($types)],
            ]));
        }

        return $records;
    },
    // Create/edit forms are a diagnoses repeater, not a flat EncounterDiagnosis record.
]);
