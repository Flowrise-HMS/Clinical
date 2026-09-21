<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Enums\AllergenType;
use Modules\Clinical\Enums\AllergySeverity;
use Modules\Clinical\Enums\AllergyVerificationStatus;
use Modules\Clinical\Enums\OnsetType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\AllergyResource;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Pages\CreateAllergy;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Pages\EditAllergy;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Pages\ListAllergies;
use Modules\Clinical\Models\Allergy;
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
    'resource' => AllergyResource::class,
    'subject' => 'Allergy',
    'model' => Allergy::class,
    'listPage' => ListAllergies::class,
    'createPage' => CreateAllergy::class,
    'editPage' => EditAllergy::class,
    'searchColumn' => 'allergen',
    'filter' => [
        'name' => 'allergen_type',
        'value' => AllergenType::FOOD->value,
        'attribute' => 'allergen_type',
    ],
    'hasBulkDelete' => true,
    'hasRecordDelete' => false,
    'hasTableDelete' => false,
    'softDeletes' => true,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): Allergy => Allergy::factory()->food()->create([
        'patient_id' => $test->patient->id,
        ...$attributes,
    ]),
    'makeRecords' => function (TestCase $test, int $count) {
        $records = collect([
            Allergy::factory()->food()->create([
                'patient_id' => $test->patient->id,
                'allergen' => 'Peanuts',
            ]),
            Allergy::factory()->medication()->create([
                'patient_id' => $test->patient->id,
                'allergen' => 'Penicillin',
            ]),
        ]);

        for ($index = 2; $index < $count; $index++) {
            $records->push(Allergy::factory()->food()->create([
                'patient_id' => $test->patient->id,
                'allergen' => 'Food allergen '.$index,
            ]));
        }

        return $records;
    },
    'createForm' => fn (TestCase $test): array => [
        'patient_id' => $test->patient->id,
        'allergen_type' => AllergenType::FOOD->value,
        'allergen' => 'Peanuts',
        'severity' => AllergySeverity::MILD->value,
        'verification_status' => AllergyVerificationStatus::VERIFIED->value,
        'onset_type' => OnsetType::ACUTE->value,
        'reaction' => 'Hives',
    ],
    'updateForm' => fn (): array => [
        'allergen' => 'Tree nuts',
        'reaction' => 'Updated reaction',
    ],
    'schemaState' => fn (mixed $test, Allergy $record): array => [
        'allergen_type' => $record->allergen_type,
        'severity' => $record->severity,
    ],
    'requiredValidation' => [
        'allergen type is required' => [['allergen_type' => null], ['allergen_type' => 'required']],
        'allergen is required' => [['allergen' => null], ['allergen' => 'required']],
        'severity is required' => [['severity' => null], ['severity' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'patient_id' => $payload['patient_id'],
        'allergen_type' => $payload['allergen_type'],
    ],
]);
