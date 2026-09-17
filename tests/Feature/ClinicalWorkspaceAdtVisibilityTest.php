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

    Permission::findOrCreate('can_discharge', 'web');
    $nurse->givePermissionTo('can_discharge');

    expect($page->canDischargeEncounter())->toBeFalse();

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

it('shows admit on adt for non-inpatient encounters that can transition to arrived', function (): void {
    $doctor = makeWorkspaceUser($this->branch, [
        'Create Encounter',
        'Update Encounter',
        'View Encounter',
    ], 'doctor');

    Encounter::factory()
        ->forPatient($this->patient)
        ->outpatient()
        ->create([
            'status' => EncounterStatus::PLANNED,
            'created_by' => $doctor->id,
            'bed_id' => null,
        ]);

    $this->actingAs($doctor);

    $page = makeWorkspacePage();
    $page->selectPatient($this->patient->id);

    expect($page->canShowAdmitOnAdt())->toBeTrue()
        ->and($page->canShowDischargeOnAdt())->toBeFalse();
});

it('shows discharge on adt only when permission and finished transition are both met', function (): void {
    $doctor = makeWorkspaceUser($this->branch, [
        'Create Encounter',
        'Update Encounter',
        'View Encounter',
        'discharge_patient',
    ], 'doctor');

    Encounter::factory()
        ->forPatient($this->patient)
        ->inpatient()
        ->create([
            'status' => EncounterStatus::ARRIVED,
            'created_by' => $doctor->id,
            'bed_id' => null,
        ]);

    $this->actingAs($doctor);

    $page = makeWorkspacePage();
    $page->selectPatient($this->patient->id);

    expect($page->canDischargeEncounter())->toBeTrue()
        ->and($page->canShowDischargeOnAdt())->toBeFalse();

    $page->getOpenEncounter()->update(['status' => EncounterStatus::IN_PROGRESS]);
    $page->selectPatient($this->patient->id);

    expect($page->canShowAdmitOnAdt())->toBeTrue()
        ->and($page->canShowDischargeOnAdt())->toBeTrue();
});

it('places the notes tab immediately after the encounter tab', function (): void {
    $doctor = makeWorkspaceUser($this->branch, [
        'View Encounter',
        'Create Encounter',
        'Update Encounter',
        'Create ClinicalNote',
    ], 'doctor');

    $this->actingAs($doctor);

    $page = makeWorkspacePage();
    $page->selectPatient($this->patient->id);

    $clinicianKeys = array_keys($page->getClinicianTabs());
    $nurseKeys = array_keys($page->getNurseTabs());

    expect(array_search('notes', $clinicianKeys, true))
        ->toBe(array_search('encounter', $clinicianKeys, true) + 1)
        ->and(array_search('notes', $nurseKeys, true))
        ->toBe(array_search('encounter', $nurseKeys, true) + 1);
});

it('hides notes tab without create clinical note permission', function (): void {
    $nurse = makeWorkspaceUser($this->branch, ['View Encounter'], 'nurse');
    $this->actingAs($nurse);

    $page = makeWorkspacePage();
    $page->selectPatient($this->patient->id);

    expect($page->canAccessNotesTab())->toBeFalse()
        ->and($page->getNurseTabs())->not->toHaveKey('notes');
});

it('shows discharge on adt after a real admission without touching the status', function (): void {
    $doctor = makeWorkspaceUser($this->branch, [
        'Create Encounter',
        'Update Encounter',
        'View Encounter',
        'discharge_patient',
    ], 'doctor');
    $this->actingAs($doctor);

    $ward = \Modules\Core\Models\Location::factory()->room()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $bed = \Modules\Core\Models\Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $ward->id, 'is_active' => true]);

    app(\Modules\Clinical\Classes\Services\AdtService::class)->admit($this->patient, $bed->id);

    $page = makeWorkspacePage();
    $page->selectPatient($this->patient->id);

    expect($page->canShowDischargeOnAdt())->toBeTrue()
        ->and($page->canShowPassOnAdt())->toBeTrue()
        ->and($page->getEncounterStatusChip()['on_pass'])->toBeFalse();

    app(\Modules\Clinical\Classes\Services\AdtService::class)->sendOnPass($page->getOpenEncounter(), 'Weekend');
    $page->selectPatient($this->patient->id);

    expect($page->getEncounterStatusChip()['on_pass'])->toBeTrue()
        ->and($page->canShowPassOnAdt())->toBeTrue()
        ->and($page->canShowDischargeOnAdt())->toBeTrue();
});

it('lets the requester withdraw a pending admission request from the adt tab', function (): void {
    $doctor = makeWorkspaceUser($this->branch, ['Create Encounter', 'Update Encounter', 'View Encounter', 'View ClinicalWorkspace', 'ViewAny Patient', 'View Patient'], 'doctor');
    $this->actingAs($doctor);

    $ward = \Modules\Core\Models\Location::factory()->room()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $bed = \Modules\Core\Models\Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $ward->id, 'is_active' => true]);

    $encounter = Encounter::factory()->forPatient($this->patient)->outpatient()->create([
        'branch_id' => $this->branch->id,
        'status' => EncounterStatus::ARRIVED,
        'created_by' => $doctor->id,
    ]);

    $request = app(\Modules\Clinical\Classes\Services\AdtService::class)
        ->requestAdmission($encounter, $ward->id, bedId: $bed->id, requestedBy: $doctor->id);

    \Livewire\Livewire::test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->assertActionVisible('cancel_admission_request')
        ->callAction('cancel_admission_request', data: ['reason' => 'No longer needed'])
        ->assertNotified('Admission request withdrawn');

    expect($request->fresh()->status)->toBe(\Modules\Clinical\Enums\AdmissionRequestStatus::Cancelled)
        ->and($bed->fresh()->bedStatus())->toBe(\Modules\Core\Enums\BedStatus::AVAILABLE);
});
