<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Classes\Services\CarePlanService;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanDiagnosis;
use Modules\Clinical\Models\CarePlanOrder;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\CarePlanWorkspace;
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

    foreach (['ViewAny CarePlan', 'Create CarePlan', 'Update CarePlan', 'View CarePlanWorkspace'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->user->givePermissionTo($permission);
    }
});

it('loads the ward workspace for an authorized user', function (): void {
    Livewire::actingAs($this->user)
        ->test(CarePlanWorkspace::class)
        ->assertOk()
        ->assertSee('Recent care plans');
});

it('renders patient search results without requiring a full_name array key', function (): void {
    Livewire::actingAs($this->user)
        ->test(CarePlanWorkspace::class)
        ->set('searchTerm', $this->patient->first_name)
        ->assertOk()
        ->assertSee($this->patient->full_name)
        ->assertSee('MRN: '.$this->patient->mrn);
});

it('disables care plan creation when the patient has no open encounter', function (): void {
    Livewire::actingAs($this->user)
        ->test(CarePlanWorkspace::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertSee('No open encounter')
        ->assertSee('Create care plan')
        ->assertSeeHtml('disabled');
});

it('activates a complete draft care plan from the authoring workspace', function (): void {
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

    CarePlanOrder::factory()
        ->count(3)
        ->for($diagnosis, 'diagnosis')
        ->sequence(fn ($sequence): array => ['sequence' => $sequence->index + 1])
        ->create();

    $medicalDiagnosis = EncounterDiagnosis::factory()->create([
        'encounter_id' => $encounter->id,
        'patient_id' => $this->patient->id,
        'ordered_by' => $this->user->id,
    ]);

    app(CarePlanService::class)->attachMedicalDiagnosis($carePlan, $medicalDiagnosis);

    Livewire::actingAs($this->user)
        ->test(CarePlanWorkspace::class, ['patientId' => $this->patient->id])
        ->call('activateCarePlan', $carePlan->id);

    expect($carePlan->fresh())
        ->status->value->toBe('active')
        ->activated_at->not->toBeNull();
});

it('lists draft care plans and resumes authoring from recent care plans', function (): void {
    $encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create(['branch_id' => $this->branch->id]);

    $carePlan = CarePlan::factory()
        ->for($this->patient)
        ->for($encounter)
        ->create([
            'branch_id' => $this->branch->id,
            'author_id' => $this->user->id,
        ]);

    Livewire::actingAs($this->user)
        ->test(CarePlanWorkspace::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertSee('Recent care plans')
        ->assertSee('Draft')
        ->assertSee('Resume')
        ->call('resumeCarePlan', $carePlan->id)
        ->assertSet('draftCarePlanId', $carePlan->id)
        ->assertSee('Care plan authoring');
});

it('saves routine care when the form submits an enum instance', function (): void {
    $encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create(['branch_id' => $this->branch->id]);

    $carePlan = CarePlan::factory()
        ->for($this->patient)
        ->for($encounter)
        ->create([
            'branch_id' => $this->branch->id,
            'author_id' => $this->user->id,
        ]);

    Livewire::actingAs($this->user)
        ->test(CarePlanWorkspace::class, [
            'patientId' => $this->patient->id,
            'draftCarePlanId' => $carePlan->id,
        ])
        ->callAction('addRoutineCare', data: [
            'item' => \Modules\Clinical\Enums\RoutineCareItem::DIET,
            'specification' => 'Soft diet',
            'not_applicable' => false,
            'notes' => null,
        ])
        ->assertHasNoActionErrors();

    expect($carePlan->fresh()->routineCares()->where('item', 'diet')->exists())->toBeTrue();
});
