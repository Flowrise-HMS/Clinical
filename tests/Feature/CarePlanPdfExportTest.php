<?php

use App\Models\User;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanDiagnosis;
use Modules\Clinical\Models\CarePlanIntervention;
use Modules\Clinical\Models\CarePlanObjective;
use Modules\Clinical\Models\CarePlanOrder;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical']);

    $this->branch = Branch::factory()->default()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );

    Permission::findOrCreate('View CarePlan', 'web');
});

it('streams a care plan pdf for an authorized viewer', function (): void {
    $this->user->givePermissionTo('View CarePlan');
    $this->actingAs($this->user);

    $encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create(['branch_id' => $this->branch->id]);

    $carePlan = CarePlan::factory()
        ->for($this->patient)
        ->for($encounter)
        ->withStrengths()
        ->withRoutineCareComplete()
        ->create([
            'branch_id' => $this->branch->id,
            'author_id' => $this->user->id,
            'no_known_allergies' => true,
        ]);

    $problem = $carePlan->problems()->firstOrFail();
    $diagnosis = CarePlanDiagnosis::factory()
        ->for($carePlan)
        ->for($problem, 'problem')
        ->create(['formulated_by' => $this->user->id]);

    $order = CarePlanOrder::factory()
        ->for($diagnosis, 'diagnosis')
        ->create(['sequence' => 1]);

    CarePlanIntervention::factory()
        ->for($order, 'order')
        ->create(['performed_by' => $this->user->id]);

    CarePlanObjective::factory()
        ->for($diagnosis, 'diagnosis')
        ->create(['author_id' => $this->user->id]);

    $response = $this->get(route('clinical.care-plans.pdf', $carePlan));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect((string) $response->getContent())->toStartWith('%PDF');
});

it('downloads a care plan pdf when download is requested', function (): void {
    $this->user->givePermissionTo('View CarePlan');
    $this->actingAs($this->user);

    $carePlan = CarePlan::factory()
        ->for($this->patient)
        ->create([
            'branch_id' => $this->branch->id,
            'author_id' => $this->user->id,
        ]);

    $response = $this->get(route('clinical.care-plans.pdf', [
        'carePlan' => $carePlan,
        'download' => 1,
    ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

it('forbids care plan pdf without view permission', function (): void {
    $this->actingAs($this->user);

    $carePlan = CarePlan::factory()
        ->for($this->patient)
        ->create([
            'branch_id' => $this->branch->id,
            'author_id' => $this->user->id,
        ]);

    $this->get(route('clinical.care-plans.pdf', $carePlan))
        ->assertForbidden();
});
