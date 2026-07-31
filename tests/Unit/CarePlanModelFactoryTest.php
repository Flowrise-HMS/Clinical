<?php

use Modules\Clinical\Enums\CarePlanCategory;
use Modules\Clinical\Enums\CarePlanStatus;
use Modules\Clinical\Enums\RoutineCareItem;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanDiagnosis;
use Tests\TestCase;

uses(TestCase::class);

it('builds a draft nursing care plan via factory', function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical']);

    $plan = CarePlan::factory()->create();

    expect($plan->status)->toBe(CarePlanStatus::DRAFT)
        ->and($plan->category)->toBe(CarePlanCategory::NURSING);
});

it('builds active care plans with the required factory relationship states', function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical']);

    $activePlan = CarePlan::factory()->active()->create();
    $planWithStrengths = CarePlan::factory()->withStrengths(2)->create();
    $planWithRoutineCare = CarePlan::factory()->withRoutineCareComplete()->create();
    $planWithDiagnosis = CarePlan::factory()->withDiagnosisAndOrders(3)->create();

    expect($activePlan->status)->toBe(CarePlanStatus::ACTIVE)
        ->and($planWithStrengths->problems()->firstOrFail()->strengths)->toHaveCount(2)
        ->and($planWithRoutineCare->routineCares)->toHaveCount(count(RoutineCareItem::cases()) - 1)
        ->and($planWithDiagnosis->diagnoses()->firstOrFail()->orders)->toHaveCount(3);
});

it('composes PES statements consistently', function (): void {
    expect(CarePlanDiagnosis::composePes(
        'Acute pain',
        'surgical incision',
        'patient reports pain score of 8',
    ))->toBe('Acute pain related to surgical incision as evidenced by patient reports pain score of 8')
        ->and(CarePlanDiagnosis::composePes(null, null, null, 'Acute pain'))->toBe('Acute pain')
        ->and(CarePlanDiagnosis::composePes('Acute pain', null, null))->toBe('Acute pain');
});
