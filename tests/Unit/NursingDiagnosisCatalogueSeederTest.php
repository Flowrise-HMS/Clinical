<?php

use Modules\Clinical\Database\Seeders\NursingDiagnosisCatalogueSeeder;
use Modules\Clinical\Models\NursingDiagnosisCatalogue;
use Tests\TestCase;

uses(TestCase::class);

it('seeds the nursing diagnosis catalogue without duplicates', function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical']);

    $this->seed(NursingDiagnosisCatalogueSeeder::class);
    $seededCount = NursingDiagnosisCatalogue::query()->count();

    $this->seed(NursingDiagnosisCatalogueSeeder::class);

    expect($seededCount)->toBeGreaterThanOrEqual(8)
        ->and(NursingDiagnosisCatalogue::query()->count())->toBe($seededCount)
        ->and(NursingDiagnosisCatalogue::query()->distinct()->count('code'))->toBe($seededCount);
});
