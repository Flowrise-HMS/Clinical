<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Classes\Services\CarePlanService;
use Modules\Clinical\Enums\CarePlanStatus;
use Modules\Clinical\Enums\RoutineCareItem;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\CarePlanWorkspace;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanDiagnosis;
use Modules\Clinical\Models\CarePlanOrder;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
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
        ->test(\Modules\Clinical\Filament\Widgets\CarePlanRecentTableWidget::class, [
            'patientId' => $this->patient->id,
        ])
        ->assertOk()
        ->assertCanSeeTableRecords([$carePlan])
        ->assertSee('Resume');

    Livewire::actingAs($this->user)
        ->test(CarePlanWorkspace::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertSee('Recent care plans')
        ->assertSee('Draft')
        ->call('resumeCarePlan', $carePlan->id)
        ->assertSet('draftCarePlanId', $carePlan->id)
        ->assertSee('Care plan authoring');
});

it('renders ward care plans with a native Filament table', function (): void {
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
        ->test(\Modules\Clinical\Filament\Widgets\CarePlanWardTableWidget::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$carePlan])
        ->assertSee($this->patient->full_name)
        ->assertSee('Resume');
});

it('renders patient previous care plans with a native Filament table', function (): void {
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
            'status' => CarePlanStatus::COMPLETED,
            'completed_at' => now(),
        ]);

    Livewire::actingAs($this->user)
        ->test(\Modules\Clinical\Filament\Widgets\CarePlanPreviousTableWidget::class, [
            'patientId' => $this->patient->id,
        ])
        ->assertOk()
        ->assertCanSeeTableRecords([$carePlan])
        ->assertSee('Completed')
        ->assertSee('Preview');
});

it('shows interventions and objectives tables inside authoring sections', function (): void {
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

    $problem = \Modules\Clinical\Models\CarePlanProblem::factory()
        ->for($carePlan)
        ->create(['identified_by' => $this->user->id]);

    $diagnosis = CarePlanDiagnosis::factory()
        ->for($carePlan)
        ->for($problem, 'problem')
        ->create(['formulated_by' => $this->user->id]);

    $order = CarePlanOrder::factory()
        ->for($diagnosis, 'diagnosis')
        ->create(['sequence' => 1]);

    $intervention = \Modules\Clinical\Models\CarePlanIntervention::factory()
        ->for($order, 'order')
        ->create([
            'performed_by' => $this->user->id,
            'description' => 'Repositioned patient every two hours',
        ]);

    $objective = \Modules\Clinical\Models\CarePlanObjective::factory()
        ->for($diagnosis, 'diagnosis')
        ->create([
            'author_id' => $this->user->id,
            'description' => 'Patient reports pain score below 4',
        ]);

    Livewire::actingAs($this->user)
        ->test(\Modules\Clinical\Filament\Widgets\CarePlanInterventionsTableWidget::class, [
            'carePlanId' => $carePlan->id,
        ])
        ->assertOk()
        ->assertCanSeeTableRecords([$intervention])
        ->assertSee('Repositioned patient every two hours');

    Livewire::actingAs($this->user)
        ->test(\Modules\Clinical\Filament\Widgets\CarePlanObjectivesTableWidget::class, [
            'carePlanId' => $carePlan->id,
        ])
        ->assertOk()
        ->assertCanSeeTableRecords([$objective])
        ->assertSee('Patient reports pain score below 4');

    Livewire::actingAs($this->user)
        ->test(CarePlanWorkspace::class, [
            'patientId' => $this->patient->id,
            'draftCarePlanId' => $carePlan->id,
        ])
        ->assertOk()
        ->assertSee('5. Interventions')
        ->assertSee('6. Evaluation')
        ->assertSee('Record intervention')
        ->assertSee('Evaluate objective');
});

it('does not offer activate for an already active care plan', function (): void {
    $encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create(['branch_id' => $this->branch->id]);

    $carePlan = CarePlan::factory()
        ->active()
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
        ->assertOk()
        ->assertSee('1. Header')
        ->assertSee('Plan active')
        ->assertDontSeeHtml('wire:click="activateCarePlan')
        ->call('activateCarePlan', $carePlan->id)
        ->assertNotified('Care plan cannot be activated');

    expect($carePlan->fresh()->status->value)->toBe('active');
});

it('shows collapsible authoring sections with readiness summaries and disables activate when incomplete', function (): void {
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
        ->assertOk()
        ->assertSeeInOrder([
            '1. Header',
            '2. Assessment',
            '3. Routine care',
            '4. NANDA + PES',
        ])
        ->assertSee('Incomplete')
        ->assertSee('Medical diagnoses')
        ->assertDontSee('Activation readiness')
        ->assertDontSee('Activation checklist')
        ->assertSeeHtml('disabled');
});

it('saves the routine care checklist in one action', function (): void {
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

    $items = collect(RoutineCareItem::cases())
        ->reject(fn ($item) => $item === RoutineCareItem::OTHER)
        ->map(fn ($item): array => [
            'item' => $item->value,
            'specification' => 'As prescribed',
            'not_applicable' => false,
            'notes' => null,
        ])
        ->values()
        ->all();

    app(CarePlanService::class)
        ->syncRoutineCareChecklist($carePlan, $items, $this->user);

    Livewire::actingAs($this->user)
        ->test(CarePlanWorkspace::class, [
            'patientId' => $this->patient->id,
            'draftCarePlanId' => $carePlan->id,
        ])
        ->mountAction('addRoutineCare')
        ->assertActionMounted('addRoutineCare');

    expect($carePlan->fresh()->routineCares)->toHaveCount(count($items))
        ->and($carePlan->fresh()->routineCares()->where('item', 'diet')->exists())->toBeTrue();
});
