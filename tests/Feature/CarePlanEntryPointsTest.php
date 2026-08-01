<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\CarePlanResource;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Pages\ListCarePlans;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\CarePlanWorkspace;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Models\CarePlan;
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

    foreach (['ViewAny CarePlan', 'View CarePlan', 'View CarePlanWorkspace'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->user->givePermissionTo($permission);
    }
});

it('registers a standalone navigable care plan workspace page outside the clinical workspace cluster', function (): void {
    $this->actingAs($this->user);

    expect(CarePlanWorkspace::shouldRegisterNavigation())->toBeTrue()
        ->and(CarePlanWorkspace::getSlug())->toBe('care-plans')
        ->and(CarePlanWorkspace::getNavigationLabel())->toBe('Care Plans')
        ->and(CarePlanWorkspace::getCluster())->toBeNull()
        ->and(CarePlanWorkspace::getUrl())->toContain('care-plans')
        ->and(collect(CarePlanWorkspace::getNavigationItems()))->not->toBeEmpty();
});

it('exposes a clinical workspace header action from care plans', function (): void {
    Livewire::actingAs($this->user)
        ->test(CarePlanWorkspace::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertActionExists('open_clinical_workspace')
        ->assertActionHasUrl(
            'open_clinical_workspace',
            ClinicalWorkspace::getUrl([
                'patientId' => $this->patient->id,
            ])
        );
});

it('keeps care plans out of the clinical workspace cluster', function (): void {
    $clustered = WorkspaceCluster::getClusteredComponents();

    expect($clustered)->not->toContain(CarePlanWorkspace::class)
        ->and($clustered)->toContain(ClinicalWorkspace::class);
});

it('lists care plans from the secondary clinical resource', function (): void {
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
        ->test(ListCarePlans::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$carePlan]);

    expect(CarePlanResource::shouldRegisterNavigation())->toBeFalse();
});

it('opens care plans from PatientActions with the selected patient', function (): void {
    $this->actingAs($this->user);

    $action = PatientActions::make()
        ->forPatient($this->patient)
        ->carePlanAction();

    expect($action->getName())->toBe('care_plan')
        ->and($action->getLabel())->toBe('Care Plan')
        ->and($action->isVisible())->toBeTrue()
        ->and($action->getUrl())->toContain('care-plans')
        ->and($action->getUrl())->toContain($this->patient->id);

    $groupNames = collect(
        PatientActions::make()
            ->forPatient($this->patient)
            ->patientActionGroups()
            ->getActions()
    )->map(fn ($groupedAction) => $groupedAction->getName())->all();

    expect($groupNames)->toContain('care_plan');
});
