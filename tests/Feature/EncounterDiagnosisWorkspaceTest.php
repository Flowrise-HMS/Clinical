<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Enums\DiagnosisCertainty;
use Modules\Clinical\Enums\DiagnosisType;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Filament\Widgets\PatientDiagnosesWidget;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Policies\EncounterDiagnosisPolicy;
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

function diagnosisWorkspaceUser(Branch $branch, array $permissions = [], string $role = 'doctor'): User
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

it('places diagnosis after notes for clinicians with create permission', function (): void {
    $doctor = diagnosisWorkspaceUser($this->branch, [
        'Create EncounterDiagnosis',
        'Create ClinicalNote',
        'Create Encounter',
        'Update Encounter',
        'View Encounter',
    ]);
    $this->actingAs($doctor);

    $page = app(ClinicalWorkspace::class);
    $page->boot();
    $page->selectPatient($this->patient->id);

    $keys = array_keys($page->getClinicianTabs());
    $notesIndex = array_search('notes', $keys, true);
    $diagnosisIndex = array_search('diagnosis', $keys, true);

    expect($notesIndex)->not->toBeFalse()
        ->and($diagnosisIndex)->not->toBeFalse()
        ->and($diagnosisIndex)->toBe($notesIndex + 1);
});

it('hides diagnosis tab without create permission', function (): void {
    $doctor = diagnosisWorkspaceUser($this->branch, [
        'Create Encounter',
        'View Encounter',
    ]);
    $this->actingAs($doctor);

    $page = app(ClinicalWorkspace::class);
    $page->boot();
    $page->selectPatient($this->patient->id);

    expect($page->canAccessDiagnosisTab())->toBeFalse()
        ->and($page->getClinicianTabs())->not->toHaveKey('diagnosis');
});

it('syncs diagnoses with type certainty and per-row notes', function (): void {
    $doctor = diagnosisWorkspaceUser($this->branch, [
        'Create EncounterDiagnosis',
        'ViewAny EncounterDiagnosis',
        'Create Encounter',
        'Update Encounter',
        'View Encounter',
    ]);
    $this->actingAs($doctor);

    $encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create(['branch_id' => $this->branch->id]);

    $page = app(ClinicalWorkspace::class);
    $page->boot();
    $page->selectPatient($this->patient->id);
    $page->diagnosisFormData = [
        'diagnoses' => [
            [
                'description' => 'Malaria',
                'type' => DiagnosisType::Primary->value,
                'is_new_case' => '1',
                'certainty' => DiagnosisCertainty::Confirmed->value,
                'notes' => 'Confirmed smear',
            ],
            [
                'description' => 'Anemia',
                'type' => DiagnosisType::Secondary->value,
                'is_new_case' => '0',
                'certainty' => DiagnosisCertainty::Provisional->value,
                'notes' => null,
            ],
        ],
    ];
    $page->saveDiagnoses();

    $active = EncounterDiagnosis::query()
        ->where('encounter_id', $encounter->id)
        ->where('is_active', true)
        ->orderBy('created_at')
        ->get();

    expect($active)->toHaveCount(2)
        ->and($active[0]->description)->toBe('Malaria')
        ->and($active[0]->type)->toBe(DiagnosisType::Primary)
        ->and($active[0]->is_new_case)->toBeTrue()
        ->and($active[0]->certainty)->toBe(DiagnosisCertainty::Confirmed)
        ->and($active[0]->notes)->toBe('Confirmed smear')
        ->and($active[1]->type)->toBe(DiagnosisType::Secondary);

    $page->diagnosisFormData = [
        'diagnoses' => [
            [
                'description' => 'Typhoid',
                'type' => DiagnosisType::Primary->value,
                'is_new_case' => '0',
                'certainty' => DiagnosisCertainty::Provisional->value,
            ],
        ],
    ];
    $page->saveDiagnoses();

    expect(EncounterDiagnosis::query()->where('encounter_id', $encounter->id)->where('is_active', true)->count())->toBe(1)
        ->and(EncounterDiagnosis::query()->where('encounter_id', $encounter->id)->where('is_active', false)->count())->toBe(2);
});

it('lists diagnoses in the patient diagnoses widget when permitted', function (): void {
    $user = diagnosisWorkspaceUser($this->branch, ['ViewAny EncounterDiagnosis']);
    $this->actingAs($user);

    $encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create(['branch_id' => $this->branch->id]);

    $diagnosis = EncounterDiagnosis::factory()->create([
        'patient_id' => $this->patient->id,
        'encounter_id' => $encounter->id,
        'description' => 'Pneumonia',
        'ordered_by' => $user->id,
    ]);

    Livewire::test(PatientDiagnosesWidget::class, [
        'patientId' => $this->patient->id,
        'encounterId' => $encounter->id,
    ])
        ->loadTable()
        ->assertCanSeeTableRecords([$diagnosis])
        ->assertSee('Pneumonia');
});

it('shows the diagnosis view action to users with the view permission', function (): void {
    $user = diagnosisWorkspaceUser($this->branch, ['ViewAny EncounterDiagnosis', 'View EncounterDiagnosis']);

    $diagnosis = EncounterDiagnosis::factory()->create([
        'patient_id' => $this->patient->id,
        'description' => 'Pneumonia',
        'ordered_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test(PatientDiagnosesWidget::class, ['patientId' => $this->patient->id])
        ->loadTable()
        ->assertCanSeeTableRecords([$diagnosis])
        ->assertTableActionVisible('view', $diagnosis);
});

it('hides the diagnosis view action from users without the view permission', function (): void {
    $user = diagnosisWorkspaceUser($this->branch, ['ViewAny EncounterDiagnosis']);

    $diagnosis = EncounterDiagnosis::factory()->create([
        'patient_id' => $this->patient->id,
        'description' => 'Pneumonia',
        'ordered_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test(PatientDiagnosesWidget::class, ['patientId' => $this->patient->id])
        ->loadTable()
        ->assertCanSeeTableRecords([$diagnosis])
        ->assertTableActionHidden('view', $diagnosis);
});

it('mounts the diagnosis view action with its infolist for permitted users', function (): void {
    $user = diagnosisWorkspaceUser($this->branch, ['ViewAny EncounterDiagnosis', 'View EncounterDiagnosis']);

    $diagnosis = EncounterDiagnosis::factory()->create([
        'patient_id' => $this->patient->id,
        'description' => 'Pneumonia',
        'notes' => 'Right lower lobe consolidation',
        'ordered_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test(PatientDiagnosesWidget::class, ['patientId' => $this->patient->id])
        ->loadTable()
        ->mountTableAction('view', $diagnosis)
        ->assertHasNoTableActionErrors()
        ->assertOk();
});

it('authorizes encounter diagnosis via policy permissions', function (): void {
    $user = diagnosisWorkspaceUser($this->branch, ['Create EncounterDiagnosis', 'ViewAny EncounterDiagnosis']);
    $this->actingAs($user);

    $policy = app(EncounterDiagnosisPolicy::class);

    expect($policy->create($user))->toBeTrue()
        ->and($policy->viewAny($user))->toBeTrue();
});

it('persists clinical note content html for the notes tab', function (): void {
    $user = diagnosisWorkspaceUser($this->branch, ['Create ClinicalNote']);
    $this->actingAs($user);

    $note = ClinicalNote::factory()->forPatient($this->patient)->create([
        'author_id' => $user->id,
        'note_type' => NoteType::PROGRESS,
        'status' => NoteStatus::DRAFT,
        'subject' => 'Ward progress',
        'content' => ['text' => '<p>Patient improving.</p>'],
    ]);

    expect($note->content_html)->toContain('Patient improving.');
});
