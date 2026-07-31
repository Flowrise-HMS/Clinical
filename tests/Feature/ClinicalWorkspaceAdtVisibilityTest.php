<?php

use App\Models\User;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff']);

    $this->branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
});

function makeWorkspaceUser(Branch $branch, array $permissions = [], string $role = 'nurse'): User
{
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Role::findOrCreate($role, 'web');
    $user->assignRole($role);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

function makeWorkspacePage(): ClinicalWorkspace
{
    $page = app(ClinicalWorkspace::class);
    $page->boot();

    return $page;
}

it('hides the adt tab for nurses without encounter create or update permissions', function (): void {
    $nurse = makeWorkspaceUser($this->branch, role: 'nurse');
    $this->actingAs($nurse);

    $page = makeWorkspacePage();
    $page->selectPatient($this->patient->id);

    expect($page->getNurseTabs())->not->toHaveKey('adt')
        ->and($page->getNurseTabs())->not->toHaveKey('discharge')
        ->and($page->getClinicianTabs())->not->toHaveKey('discharge');
});

it('shows the adt tab but not a standalone discharge tab for clinicians', function (): void {
    $doctor = makeWorkspaceUser($this->branch, [
        'Create Encounter',
        'Update Encounter',
        'View Encounter',
        'discharge_patient',
    ], 'doctor');

    $this->actingAs($doctor);

    $page = makeWorkspacePage();
    $page->selectPatient($this->patient->id);
    $tabs = $page->getClinicianTabs();

    expect($tabs)->toHaveKey('adt')
        ->and($tabs)->not->toHaveKey('discharge');
});

it('allows discharge capability only when the user has a discharge permission', function (): void {
    $nurse = makeWorkspaceUser($this->branch, [
        'Create Encounter',
        'Update Encounter',
        'View Encounter',
    ], 'nurse');

    Encounter::factory()
        ->forPatient($this->patient)
        ->inpatient()
        ->create([
            'status' => EncounterStatus::IN_PROGRESS,
            'created_by' => $nurse->id,
            'bed_id' => null,
        ]);

    $this->actingAs($nurse);

    $page = makeWorkspacePage();
    $page->selectPatient($this->patient->id);

    expect($page->canUpdateEncounter())->toBeTrue()
        ->and($page->canDischargeEncounter())->toBeFalse()
        ->and($page->canAccessAdtTab())->toBeTrue()
        ->and($page->getNurseTabs())->toHaveKey('adt')
        ->and($page->getNurseTabs())->not->toHaveKey('discharge');

    Permission::findOrCreate('discharge_patient', 'web');
    $nurse->givePermissionTo('discharge_patient');

    expect($page->canDischargeEncounter())->toBeTrue();
});

it('requires create encounter permission when there is no open encounter', function (): void {
    $nurse = makeWorkspaceUser($this->branch, ['View Encounter'], 'nurse');
    $this->actingAs($nurse);

    $page = makeWorkspacePage();
    $page->selectPatient($this->patient->id);

    expect($page->canCreateEncounter())->toBeFalse()
        ->and($page->canAccessAdtTab())->toBeFalse()
        ->and($page->getNurseTabs())->not->toHaveKey('adt');
});
