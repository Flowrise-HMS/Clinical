<?php

use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Modules\Clinical\Enums\DiagnosisCertainty;
use Modules\Clinical\Enums\DiagnosisType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages\EditEncounter;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\RelationManagers\DiagnosesRelationManager;
use Modules\Clinical\Filament\RelationManagers\Patient\PatientDiagnosesRelationManager;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Core\Models\Branch;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\EditPatient;
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
    $this->encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create(['branch_id' => $this->branch->id]);
    $this->diagnosis = EncounterDiagnosis::create([
        'encounter_id' => $this->encounter->id,
        'patient_id' => $this->patient->id,
        'description' => 'Cholera',
        'type' => DiagnosisType::Primary,
        'is_new_case' => false,
        'certainty' => DiagnosisCertainty::Provisional,
        'ordered_by' => User::factory()->create(['branch_id' => $this->branch->id])->id,
    ]);
});

function relationManagerEncounterDiagnosis(array $attributes): EncounterDiagnosis
{
    return EncounterDiagnosis::create($attributes);
}

it('renders the encounter diagnoses through the encounter relation manager', function (): void {
    Livewire::test(DiagnosesRelationManager::class, [
        'ownerRecord' => $this->encounter,
        'pageClass' => EditEncounter::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$this->diagnosis]);
});

it('scopes the encounter relation manager to the owning encounter', function (): void {
    $otherEncounter = Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create(['branch_id' => $this->branch->id]);
    $otherDiagnosis = relationManagerEncounterDiagnosis([
        'encounter_id' => $otherEncounter->id,
        'patient_id' => $this->patient->id,
        'description' => 'Severe malaria',
        'type' => DiagnosisType::Primary,
        'is_new_case' => false,
        'certainty' => DiagnosisCertainty::Confirmed,
        'ordered_by' => $this->diagnosis->ordered_by,
    ]);

    Livewire::test(DiagnosesRelationManager::class, [
        'ownerRecord' => $this->encounter,
        'pageClass' => EditEncounter::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$this->diagnosis])
        ->assertCanNotSeeTableRecords([$otherDiagnosis]);
});

it('renders the patient diagnoses through the patient relation manager', function (): void {
    Livewire::test(PatientDiagnosesRelationManager::class, [
        'ownerRecord' => $this->patient,
        'pageClass' => EditPatient::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$this->diagnosis]);
});

it('scopes the patient relation manager to the owning patient', function (): void {
    $otherPatient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
    $otherDiagnosis = relationManagerEncounterDiagnosis([
        'encounter_id' => $this->encounter->id,
        'patient_id' => $otherPatient->id,
        'description' => 'Hypertension',
        'type' => DiagnosisType::Secondary,
        'is_new_case' => false,
        'certainty' => DiagnosisCertainty::Confirmed,
        'ordered_by' => $this->diagnosis->ordered_by,
    ]);

    Livewire::test(PatientDiagnosesRelationManager::class, [
        'ownerRecord' => $this->patient,
        'pageClass' => EditPatient::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$this->diagnosis])
        ->assertCanNotSeeTableRecords([$otherDiagnosis]);
});

it('creates an encounter diagnosis scoped to the encounter', function (): void {
    $user = User::factory()->create(['branch_id' => $this->branch->id]);
    Role::findOrCreate('doctor', 'web');
    $user->assignRole('doctor');
    foreach (['ViewAny EncounterDiagnosis', 'Create EncounterDiagnosis'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }
    $this->actingAs($user);

    Livewire::test(DiagnosesRelationManager::class, [
        'ownerRecord' => $this->encounter,
        'pageClass' => EditEncounter::class,
    ])->callAction(TestAction::make(CreateAction::class)->table(), [
        'description' => 'Severe malaria',
        'type' => DiagnosisType::Primary->value,
        'certainty' => DiagnosisCertainty::Confirmed->value,
        'is_new_case' => '1',
        'notes' => 'Test note',
    ]);

    $created = EncounterDiagnosis::where('encounter_id', $this->encounter->id)
        ->where('description', 'Severe malaria')
        ->first();

    expect($created)->not->toBeNull()
        ->and($created->patient_id)->toBe($this->patient->id)
        ->and($created->ordered_by)->toBe($user->id);
});
