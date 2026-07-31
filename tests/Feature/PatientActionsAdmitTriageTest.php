<?php

use App\Models\User;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
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

    Permission::findOrCreate('Update Encounter', 'web');
    $this->user->givePermissionTo('Update Encounter');

    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );

    $this->actingAs($this->user);
});

it('surfaces admit via PatientActions for a planned outpatient encounter', function (): void {
    $encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->outpatient()
        ->create([
            'status' => EncounterStatus::PLANNED,
            'created_by' => $this->user->id,
        ]);

    $actions = PatientActions::make()
        ->forPatient($this->patient->fresh())
        ->withEncounter($encounter);

    $admit = $actions->admitAction();
    $legacy = $actions->assignToWardAction();

    expect($admit->getName())->toBe('admit')
        ->and($admit->getLabel())->toBe('Admit Patient')
        ->and($admit->isVisible())->toBeTrue()
        ->and($admit->isAuthorized())->toBeTrue()
        ->and($legacy->getName())->toBe('assign_to_ward')
        ->and($legacy->isVisible())->toBeTrue();

    $groupNames = collect($actions->patientActionGroups()->getActions())
        ->map(fn ($action) => $action->getName())
        ->all();

    expect($groupNames)->toContain('admit')
        ->and($groupNames)->toContain('triage')
        ->and($groupNames)->not->toContain('assign_to_ward');
});

it('surfaces triage via PatientActions for an arrived encounter', function (): void {
    $encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->create([
            'type' => EncounterType::EMERGENCY,
            'status' => EncounterStatus::ARRIVED,
            'created_by' => $this->user->id,
        ]);

    $triage = PatientActions::make()
        ->forPatient($this->patient->fresh())
        ->withEncounter($encounter)
        ->triageAction();

    expect($triage->getName())->toBe('triage')
        ->and($triage->getLabel())->toBe('Triage')
        ->and($triage->isVisible())->toBeTrue()
        ->and($triage->isAuthorized())->toBeTrue();
});

it('hides triage when the encounter is not arrived', function (): void {
    $encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->create([
            'status' => EncounterStatus::PLANNED,
            'created_by' => $this->user->id,
        ]);

    $triage = PatientActions::make()
        ->forPatient($this->patient->fresh())
        ->withEncounter($encounter)
        ->triageAction();

    expect($triage->isVisible())->toBeFalse();
});

it('uses the encounter passed to withEncounter instead of only the active encounter', function (): void {
    $active = Encounter::factory()
        ->forPatient($this->patient)
        ->create([
            'status' => EncounterStatus::IN_PROGRESS,
            'created_by' => $this->user->id,
            'created_at' => now()->subHour(),
        ]);

    $arrived = Encounter::factory()
        ->forPatient($this->patient)
        ->create([
            'type' => EncounterType::EMERGENCY,
            'status' => EncounterStatus::ARRIVED,
            'created_by' => $this->user->id,
            'created_at' => now()->subDays(2),
        ]);

    $actions = PatientActions::make()
        ->forPatient($this->patient->fresh())
        ->withEncounter($arrived);

    expect($actions->triageAction()->isVisible())->toBeTrue()
        ->and($actions->admitAction()->isVisible())->toBeFalse();

    $actionsForActive = PatientActions::make()
        ->forPatient($this->patient->fresh())
        ->withEncounter($active);

    expect($actionsForActive->triageAction()->isVisible())->toBeFalse();
});
