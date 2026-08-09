<?php

use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\MedicationRoute;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy']);

    $this->branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
});

it('accepts MedicationRoute enums when resolving default administration context', function (): void {
    $encounter = Encounter::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'type' => EncounterType::OUTPATIENT,
        'status' => 'in_progress',
        'admitted_at' => now()->subHour(),
    ]);

    $policy = app(MedicationFulfillmentPolicy::class);

    expect($policy->defaultAdministrationContext($encounter, MedicationRoute::IV))
        ->toBe(AdministrationContext::IN_FACILITY)
        ->and($policy->defaultAdministrationContext($encounter, MedicationRoute::PO))
        ->toBe(AdministrationContext::TAKE_HOME)
        ->and($policy->defaultAdministrationContext($encounter, 'im'))
        ->toBe(AdministrationContext::IN_FACILITY);
});
