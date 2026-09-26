<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Enums\AdmissionRequestStatus;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Filament\Widgets\PendingAdmissionsWidget;
use Modules\Clinical\Models\AdmissionRequest;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff']);

    $this->branch = Branch::factory()->default()->create();
    $this->doctor = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->nurse = User::factory()->create(['branch_id' => $this->branch->id]);

    foreach (['View ClinicalWorkspace', 'View Encounter', 'Update Encounter', 'ViewAny Patient', 'View Patient'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $this->doctor->givePermissionTo(['View ClinicalWorkspace', 'View Encounter', 'Update Encounter', 'ViewAny Patient', 'View Patient']);
    $this->nurse->givePermissionTo(['View ClinicalWorkspace', 'View Encounter', 'Update Encounter', 'ViewAny Patient', 'View Patient']);

    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
    $this->ward = Location::factory()->room()->create(['branch_id' => $this->branch->id, 'is_active' => true, 'name' => 'Ward 3']);
    $this->bed = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $this->ward->id, 'is_active' => true, 'name' => 'Bed 3-1']);

    $this->encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->outpatient()
        ->create([
            'branch_id' => $this->branch->id,
            'status' => EncounterStatus::ARRIVED,
            'bed_id' => null,
            'created_by' => $this->doctor->id,
        ]);
});

function requestAdmissionFor(Encounter $encounter, Location $ward, User $doctor, ?string $bedId = null): AdmissionRequest
{
    return app(AdtService::class)->requestAdmission($encounter, $ward->id, bedId: $bedId, requestedBy: $doctor->id);
}

it('lists pending admission requests for the current branch', function (): void {
    // Build the other branch's request before authenticating: BaseModel scopes
    // every query to the signed-in user's branch, which is exactly what keeps
    // it out of this nurse's queue below.
    $otherBranch = Branch::factory()->create();
    $otherPatient = Patient::withoutEvents(fn (): Patient => Patient::factory()->create(['branch_id' => $otherBranch->id]));
    $otherEncounter = Encounter::factory()->forPatient($otherPatient)->outpatient()->create([
        'branch_id' => $otherBranch->id,
        'status' => EncounterStatus::ARRIVED,
        'bed_id' => null,
    ]);
    $otherWard = Location::factory()->room()->create(['branch_id' => $otherBranch->id, 'is_active' => true]);
    $foreign = requestAdmissionFor($otherEncounter, $otherWard, $this->doctor);

    $this->actingAs($this->nurse);
    session(['current_branch_id' => $this->branch->id]);

    $request = requestAdmissionFor($this->encounter, $this->ward, $this->doctor);

    Livewire::test(PendingAdmissionsWidget::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$request])
        ->assertCanNotSeeTableRecords([$foreign])
        ->assertSee('Ward 3')
        ->assertSeeHtml('href="'.e(ClinicalWorkspace::getUrl(['patientId' => $this->patient->id])).'"')
        ->assertTableColumnExists('requestedWard.name')
        ->assertTableColumnExists('requester.name')
        ->assertTableFilterExists('requested_ward_id')
        ->assertTableFilterExists('priority');
});

it('accepts an admission from the widget and admits the patient to the chosen bed', function (): void {
    $this->actingAs($this->nurse);
    session(['current_branch_id' => $this->branch->id]);

    $request = requestAdmissionFor($this->encounter, $this->ward, $this->doctor);

    Livewire::test(PendingAdmissionsWidget::class)
        ->callTableAction('accept', $request, data: ['bed_id' => $this->bed->id, 'notes' => 'Welcome'])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Admission accepted — patient admitted to bed')
        ->assertCanNotSeeTableRecords([$request]);

    $request->refresh();
    $this->encounter->refresh();

    expect($request->status)->toBe(AdmissionRequestStatus::Accepted)
        ->and($request->assigned_bed_id)->toBe($this->bed->id)
        ->and($request->decided_by)->toBe($this->nurse->id)
        ->and($this->encounter->bed_id)->toBe($this->bed->id)
        ->and($this->encounter->type)->toBe(EncounterType::INPATIENT);
});

it('rejects an admission from the widget with a reason and leaves the encounter open', function (): void {
    $this->actingAs($this->nurse);
    session(['current_branch_id' => $this->branch->id]);

    $request = requestAdmissionFor($this->encounter, $this->ward, $this->doctor);

    Livewire::test(PendingAdmissionsWidget::class)
        ->callTableAction('reject', $request, data: ['reason' => 'No free beds until tomorrow'])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Admission rejected');

    $request->refresh();
    $this->encounter->refresh();

    expect($request->status)->toBe(AdmissionRequestStatus::Rejected)
        ->and($request->decision_notes)->toBe('No free beds until tomorrow')
        ->and($this->encounter->status)->toBe(EncounterStatus::ARRIVED)
        ->and($this->encounter->bed_id)->toBeNull();
});

it('requires a rejection reason', function (): void {
    $this->actingAs($this->nurse);
    session(['current_branch_id' => $this->branch->id]);

    $request = requestAdmissionFor($this->encounter, $this->ward, $this->doctor);

    Livewire::test(PendingAdmissionsWidget::class)
        ->callTableAction('reject', $request, data: ['reason' => ''])
        ->assertHasTableActionErrors(['reason' => 'required']);

    expect($request->refresh()->status)->toBe(AdmissionRequestStatus::Pending);
});

it('is hidden from users who cannot update encounters', function (): void {
    $viewer = User::factory()->create(['branch_id' => $this->branch->id]);
    $viewer->givePermissionTo(['View ClinicalWorkspace', 'View Encounter']);
    $this->actingAs($viewer);

    expect(PendingAdmissionsWidget::canView())->toBeFalse();

    $this->actingAs($this->nurse);

    expect(PendingAdmissionsWidget::canView())->toBeTrue();
});

it('lets the ward accept or reject from the patient adt tab', function (): void {
    $this->actingAs($this->nurse);
    session(['current_branch_id' => $this->branch->id]);

    $request = requestAdmissionFor($this->encounter, $this->ward, $this->doctor, $this->bed->id);

    $page = Livewire::test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->assertSet('mode', 'patient');

    expect($page->instance()->canShowAdmissionDecisionOnAdt())->toBeTrue()
        ->and($page->instance()->canShowAdmitOnAdt())->toBeFalse()
        ->and($page->instance()->getPendingAdmissionRequest()?->id)->toBe($request->id);

    $page->callAction('accept_admission', data: ['bed_id' => $this->bed->id])
        ->assertNotified('Admission accepted — patient admitted to bed');

    $this->encounter->refresh();

    expect($this->encounter->bed_id)->toBe($this->bed->id)
        ->and($this->encounter->type)->toBe(EncounterType::INPATIENT)
        ->and($page->instance()->canShowAdmissionDecisionOnAdt())->toBeFalse();
});

it('surfaces a rejection to the clinician on the adt tab', function (): void {
    $this->actingAs($this->doctor);
    session(['current_branch_id' => $this->branch->id]);

    $request = requestAdmissionFor($this->encounter, $this->ward, $this->doctor);
    app(AdtService::class)->rejectAdmission($request, 'Ward closed for cleaning', actedBy: $this->nurse->id);

    $page = Livewire::test(ClinicalWorkspace::class, ['patientId' => $this->patient->id]);

    expect($page->instance()->getPendingAdmissionRequest())->toBeNull()
        ->and($page->instance()->getLatestDecidedAdmissionRequest()?->decision_notes)->toBe('Ward closed for cleaning')
        ->and($page->instance()->canShowAdmitOnAdt())->toBeTrue();
});

it('completes an outpatient encounter from the workspace', function (): void {
    $this->actingAs($this->doctor);
    session(['current_branch_id' => $this->branch->id]);

    $page = Livewire::test(ClinicalWorkspace::class, ['patientId' => $this->patient->id]);

    expect($page->instance()->canShowCompleteOnAdt())->toBeTrue();

    $page->callAction('complete_encounter', data: ['notes' => 'Consultation done'])
        ->assertNotified('Encounter completed');

    $this->encounter->refresh();

    expect($this->encounter->status)->toBe(EncounterStatus::FINISHED)
        ->and($this->encounter->metadata['completion_notes'] ?? null)->toBe('Consultation done')
        ->and($page->instance()->getOpenEncounter())->toBeNull();
});
