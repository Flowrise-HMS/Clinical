<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\CarePlanResource;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Pages\ListCarePlans;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Pages\ViewCarePlan;
use Modules\Clinical\Models\CarePlan;
use Modules\Core\Models\Branch;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Clinical');
    $this->migrateModules(['Core', 'Patient', 'Staff', 'Clinical']);
    $this->branch = Branch::factory()->create();
    $this->setCurrentBranch($this->branch);
});

FilamentResourceTestSuite::register([
    'resource' => CarePlanResource::class,
    'subject' => 'CarePlan',
    'model' => CarePlan::class,
    'listPage' => ListCarePlans::class,
    'viewPage' => ViewCarePlan::class,
    'searchColumn' => 'title',
    'sortColumn' => 'activated_at',
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): CarePlan => CarePlan::factory()->create([
        'branch_id' => $test->branch->id,
        'title' => 'ZephyrCare'.fake()->unique()->numerify('####'),
        ...$attributes,
    ]),
    'makeRecords' => function (TestCase $test, int $count) {
        $titles = ['ZephyrAlpha', 'NimbusBeta', 'QuasarGamma', 'HeliosDelta'];
        $records = collect();

        for ($index = 0; $index < $count; $index++) {
            $records->push(CarePlan::factory()->create([
                'branch_id' => $test->branch->id,
                'title' => $titles[$index] ?? 'Orion'.$index,
                'activated_at' => now()->addDays($index),
            ]));
        }

        return $records;
    },
]);
