<?php

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;
use Modules\Clinical\Classes\Services\TriageService;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Enums\TriageCategory;
use Modules\Clinical\Enums\TriageDisposition;
use Modules\Clinical\Enums\VitalSignType;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Filament\Widgets\TriageQueueWidget;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\TriageAssessment;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff']);

    $this->branch = Branch::factory()->default()->create();
    $this->nurse = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->doctor = User::factory()->create(['branch_id' => $this->branch->id]);

    foreach (['View ClinicalWorkspace', 'View Encounter', 'Update Encounter', 'ViewAny Patient', 'View Patient', 'view_all_patients'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->nurse->assignRole(Role::findOrCreate('nurse', 'web'));
    $this->doctor->assignRole(Role::findOrCreate('doctor', 'web'));

    foreach ([$this->nurse, $this->doctor] as $user) {
        $user->givePermissionTo(['View ClinicalWorkspace', 'View Encounter', 'Update Encounter', 'ViewAny Patient', 'View Patient', 'view_all_patients']);
    }

    $this->patient = Patient::withoutEvents(fn (): Patient => Patient::factory()->create([
        'branch_id' => $this->branch->id,
        'date_of_birth' => now()->subYears(40),
    ]));

    $this->encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->emergency()
        ->create([
            'branch_id' => $this->branch->id,
            'status' => EncounterStatus::ARRIVED,
            'priority' => EncounterPriority::ROUTINE,
            'chief_complaint' => null,
            'created_by' => $this->nurse->id,
        ]);

    session(['current_branch_id' => $this->branch->id]);
});

/**
 * Adult vitals scoring TEWS 0 (Green) unless overridden.
 *
 * @return array<string, mixed>
 */
function calmAdultTriage(array $overrides = []): array
{
    return [
        'age_band' => 'adult',
        'respiratory_rate' => 12,
        'heart_rate' => 80,
        'systolic_bp' => 120,
        'diastolic_bp' => 80,
        'temperature' => 36.8,
        'spo2' => 98,
        'mobility' => 'walking',
        'avpu' => 'alert',
        'trauma' => false,
        'disposition' => TriageDisposition::CONSULTATION->value,
        ...$overrides,
    ];
}

it('records a triage, its vitals and note, and moves the encounter to triaged', function (): void {
    $this->actingAs($this->nurse);

    $assessment = app(TriageService::class)->assess($this->encounter, calmAdultTriage([
        'heart_rate' => 115,
        'temperature' => 39.1,
        'mobility' => 'with_help',
        'notes' => 'Febrile, walking with support.',
        'chief_complaint' => 'Fever',
    ]));

    // HR 115 (2) + temp 39.1 (2) + with help (1) = 5 -> Orange
    expect($assessment->tews_score)->toBe(5)
        ->and($assessment->final_category)->toBe(TriageCategory::ORANGE)
        ->and($assessment->triaged_by)->toBe($this->nurse->id)
        ->and($assessment->vitalSign?->type)->toBe(VitalSignType::TRIAGE)
        ->and($assessment->vitalSign?->heart_rate)->toBe(115);

    $encounter = $this->encounter->fresh();

    expect($encounter->status)->toBe(EncounterStatus::TRIAGED)
        ->and($encounter->priority)->toBe(EncounterPriority::URGENT)
        ->and($encounter->chief_complaint)->toBe('Fever')
        ->and(ClinicalNote::query()->where('encounter_id', $encounter->id)->where('note_type', NoteType::TRIAGE)->exists())->toBeTrue();
});

it('lets an emergency sign make the patient red whatever the TEWS', function (): void {
    $this->actingAs($this->nurse);

    $assessment = app(TriageService::class)->assess($this->encounter, calmAdultTriage([
        'discriminators_red' => ['seizure_current'],
        'discriminators_yellow' => ['moderate_pain'],
    ]));

    expect($assessment->tews_score)->toBe(0)
        ->and($assessment->tews_category)->toBe(TriageCategory::GREEN)
        ->and($assessment->discriminator_category)->toBe(TriageCategory::RED)
        ->and($assessment->final_category)->toBe(TriageCategory::RED)
        ->and($assessment->discriminators)->toBe(['seizure_current', 'moderate_pain'])
        ->and($this->encounter->fresh()->priority)->toBe(EncounterPriority::EMERGENCY);
});

it('requires a reason to override the calculated category', function (): void {
    $this->actingAs($this->doctor);

    expect(fn () => app(TriageService::class)->assess($this->encounter, calmAdultTriage(['override_category' => 'orange'])))
        ->toThrow(InvalidArgumentException::class);

    $assessment = app(TriageService::class)->assess($this->encounter, calmAdultTriage([
        'override_category' => 'orange',
        'override_reason' => 'Looks unwell despite normal observations.',
    ]));

    expect($assessment->tews_category)->toBe(TriageCategory::GREEN)
        ->and($assessment->final_category)->toBe(TriageCategory::ORANGE);
});

it('re-triages an encounter in progress by updating its priority only', function (): void {
    $this->actingAs($this->nurse);
    $this->encounter->update(['status' => EncounterStatus::IN_PROGRESS]);

    app(TriageService::class)->assess($this->encounter, calmAdultTriage(['discriminators_orange' => ['chest_pain']]));

    $encounter = $this->encounter->fresh();

    expect($encounter->status)->toBe(EncounterStatus::IN_PROGRESS)
        ->and($encounter->priority)->toBe(EncounterPriority::URGENT)
        ->and($encounter->triageAssessments()->count())->toBe(1);
});

it('shows the triage tab to clinicians as well as nurses', function (): void {
    $doctorTabs = Livewire::actingAs($this->doctor)
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->instance()
        ->getClinicianTabs();

    $nurseTabs = Livewire::actingAs($this->nurse)
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->instance()
        ->getNurseTabs();

    expect($doctorTabs)->toHaveKey('triage')
        ->and($nurseTabs)->toHaveKey('triage');
});

it('triages from the workspace triage tab with the SATS form', function (): void {
    Livewire::actingAs($this->doctor)
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->set('activeTab', 'triage')
        ->assertSee('Clinical signs first, then the Triage Early Warning Score (TEWS).')
        ->assertDontSee('South African Triage Scale')
        ->assertSee('Emergency signs (Red)')
        ->assertSee('TEWS 0')
        ->fillForm(calmAdultTriage(['respiratory_rate' => 32, 'heart_rate' => 135]), 'triageForm')
        ->assertSee('TEWS 6')
        ->call('saveTriage')
        ->assertHasNoFormErrors(form: 'triageForm')
        ->assertNotified('Triaged Orange');

    $assessment = TriageAssessment::query()->where('encounter_id', $this->encounter->id)->sole();

    expect($assessment->tews_score)->toBe(6)
        ->and($this->encounter->fresh()->status)->toBe(EncounterStatus::TRIAGED);
});

it('starts the encounter when triage sends the patient to resuscitation', function (): void {
    Livewire::actingAs($this->nurse)
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->set('activeTab', 'triage')
        ->fillForm(calmAdultTriage([
            'discriminators_red' => ['cardiac_arrest'],
            'disposition' => TriageDisposition::RESUSCITATION->value,
        ]), 'triageForm')
        ->call('saveTriage')
        ->assertNotified('Triaged Red');

    expect($this->encounter->fresh()->status)->toBe(EncounterStatus::IN_PROGRESS);
});

it('triages through the encounter action with the SATS form', function (): void {
    Livewire::actingAs($this->nurse)
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->callAction(TestAction::make('triage'), data: calmAdultTriage(['discriminators_yellow' => ['abdominal_pain']]))
        ->assertHasNoFormErrors()
        ->assertNotified('Triaged Yellow');

    expect($this->encounter->fresh()->status)->toBe(EncounterStatus::TRIAGED);
});

it('orders the triage queue by SATS priority with untriaged patients after red', function (): void {
    $this->actingAs($this->nurse);

    $makeEncounter = function (): Encounter {
        $patient = Patient::withoutEvents(fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id]));

        return Encounter::factory()->forPatient($patient)->outpatient()->create([
            'branch_id' => $this->branch->id,
            'status' => EncounterStatus::ARRIVED,
            'admitted_at' => now()->subMinutes(30),
        ]);
    };

    $green = $makeEncounter();
    $untriaged = $makeEncounter();
    $red = $makeEncounter();
    $orange = $makeEncounter();

    app(TriageService::class)->assess($green, calmAdultTriage());
    app(TriageService::class)->assess($red, calmAdultTriage(['discriminators_red' => ['cardiac_arrest']]));
    app(TriageService::class)->assess($orange, calmAdultTriage(['discriminators_orange' => ['chest_pain']]));

    // The shared encounter from beforeEach is also arrived and untriaged.
    Model::preventLazyLoading();

    try {
        Livewire::test(TriageQueueWidget::class)
            ->assertCanSeeTableRecords([$red, $this->encounter, $untriaged, $orange, $green])
            ->assertSeeInOrder([$red->patient->full_name, $untriaged->patient->full_name, $orange->patient->full_name, $green->patient->full_name]);
    } finally {
        Model::preventLazyLoading(false);
    }
});

it('flags a triaged patient waiting past the SATS target time', function (): void {
    $this->actingAs($this->nurse);

    $assessment = app(TriageService::class)->assess($this->encounter, calmAdultTriage(['discriminators_orange' => ['chest_pain']]));
    $assessment->update(['triaged_at' => now()->subMinutes(25)]);

    expect($assessment->fresh()->isOverdue())->toBeTrue();

    Livewire::test(TriageQueueWidget::class)->assertSee('Overdue since');
});

it('lists triaged red and orange patients as critical', function (): void {
    $this->actingAs($this->nurse);

    expect(app(ClinicalWorkspaceService::class)->getCriticalPatients()->pluck('id'))->not->toContain($this->patient->id);

    app(TriageService::class)->assess($this->encounter, calmAdultTriage(['discriminators_orange' => ['chest_pain']]));

    expect(app(ClinicalWorkspaceService::class)->getCriticalPatients()->pluck('id'))->toContain($this->patient->id);
});

it('does not offer triage for inpatients', function (): void {
    $this->encounter->update(['type' => EncounterType::INPATIENT]);

    Livewire::actingAs($this->nurse)
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->assertActionHidden(TestAction::make('triage'));
});

it('does not describe a finished visit as a current admission in the patient header', function (): void {
    $this->encounter->update([
        'type' => EncounterType::OUTPATIENT,
        'status' => EncounterStatus::FINISHED,
        'admitted_at' => now()->subDays(4),
        'discharged_at' => null,
    ]);

    Livewire::actingAs($this->nurse)
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->assertDontSee('Admitted for')
        ->assertSee('Last visit: Outpatient');
});

it('shows the length of stay only for an open inpatient encounter', function (): void {
    $this->encounter->update([
        'type' => EncounterType::INPATIENT,
        'status' => EncounterStatus::IN_PROGRESS,
        'admitted_at' => now()->subDays(2),
    ]);

    Livewire::actingAs($this->nurse)
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->assertSee('Admitted for 2 days');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function recordedVitals(Encounter $encounter, array $attributes = []): VitalSign
{
    return VitalSign::factory()->create([
        'patient_id' => $encounter->patient_id,
        'encounter_id' => $encounter->id,
        'branch_id' => $encounter->branch_id,
        'respiratory_rate' => 18,
        'heart_rate' => 88,
        'systolic_bp' => 132,
        'diastolic_bp' => 84,
        'temperature' => 37.2,
        'spo2' => 97,
        ...$attributes,
    ]);
}

it('prefills the triage vital signs with the vitals already taken', function (): void {
    $this->actingAs($this->nurse);
    recordedVitals($this->encounter, ['recorded_at' => now()->subMinutes(20)]);

    Livewire::actingAs($this->nurse)
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->set('activeTab', 'triage')
        ->assertSet('triageData.heart_rate', 88)
        ->assertSet('triageData.respiratory_rate', 18)
        ->assertSet('triageData.systolic_bp', 132)
        ->assertSet('triageData.temperature', 37.2)
        ->assertSee('Prefilled from vitals recorded');
});

it('links the prefilled vitals instead of duplicating them when they are unchanged', function (): void {
    $this->actingAs($this->nurse);
    $vitals = recordedVitals($this->encounter);

    Livewire::actingAs($this->nurse)
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->set('activeTab', 'triage')
        ->fillForm(['mobility' => 'walking', 'avpu' => 'alert'], 'triageForm')
        ->call('saveTriage')
        ->assertNotified();

    $assessment = TriageAssessment::query()->where('encounter_id', $this->encounter->id)->sole();

    expect($assessment->vital_sign_id)->toBe($vitals->id)
        ->and(VitalSign::query()->where('encounter_id', $this->encounter->id)->count())->toBe(1);
});

it('records a new reading when a prefilled vital is changed at triage', function (): void {
    $this->actingAs($this->nurse);
    $vitals = recordedVitals($this->encounter);

    Livewire::actingAs($this->nurse)
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->set('activeTab', 'triage')
        ->fillForm(['heart_rate' => 124, 'mobility' => 'walking', 'avpu' => 'alert'], 'triageForm')
        ->call('saveTriage');

    $assessment = TriageAssessment::query()->where('encounter_id', $this->encounter->id)->sole();

    expect($assessment->vital_sign_id)->not->toBe($vitals->id)
        ->and($assessment->vitalSign->heart_rate)->toBe(124)
        ->and($assessment->vitalSign->respiratory_rate)->toBe(18);
});

it('does not prefill vitals older than a day from another visit', function (): void {
    $this->actingAs($this->nurse);

    VitalSign::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'encounter_id' => null,
        'heart_rate' => 70,
        'recorded_at' => now()->subDays(3),
    ]);

    expect(app(TriageService::class)->vitalsPrefillFor($this->patient, $this->encounter))->toBe([]);
});
