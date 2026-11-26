<?php

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Clinical\Enums\DiagnosisCertainty;
use Modules\Clinical\Enums\DiagnosisType;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Filament\Widgets\PatientVitalsOverviewWidget;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\DiagnosisCode;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Enums\CoverageType;
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

    $this->doctor = User::factory()->create(['branch_id' => $this->branch->id]);
    Role::findOrCreate('doctor', 'web');
    $this->doctor->assignRole('doctor');

    foreach ([
        'View ClinicalWorkspace',
        'Create EncounterDiagnosis',
        'ViewAny EncounterDiagnosis',
        'Create Encounter',
        'Update Encounter',
        'View Encounter',
        'Create VitalSign',
        'View VitalSign',
        'Create ClinicalNote',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->doctor->givePermissionTo($permission);
    }

    $this->actingAs($this->doctor);
});

function contextWorkspacePage(): ClinicalWorkspace
{
    $page = app(ClinicalWorkspace::class);
    $page->boot();

    return $page;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contextDiagnosisRow(array $overrides = []): array
{
    return array_merge([
        'encounter_diagnosis_id' => null,
        'code_search' => null,
        'description' => null,
        'type' => DiagnosisType::Primary->value,
        'is_new_case' => '0',
        'certainty' => DiagnosisCertainty::Provisional->value,
        'notes' => null,
        'diagnosis_code_id' => null,
        'icd_entity_id' => null,
        'icd_uri' => null,
        'icd_code' => null,
        'icd10_code' => null,
    ], $overrides);
}

function contextActiveEncounter(Patient $patient, Branch $branch): Encounter
{
    return Encounter::factory()
        ->forPatient($patient)
        ->active()
        ->create(['branch_id' => $branch->id]);
}

it('does not carry one patient\'s diagnoses into another patient\'s form', function (): void {
    $encounter = contextActiveEncounter($this->patient, $this->branch);

    EncounterDiagnosis::create([
        'encounter_id' => $encounter->id,
        'patient_id' => $this->patient->id,
        'description' => 'Malaria',
        'type' => DiagnosisType::Primary,
        'certainty' => DiagnosisCertainty::Confirmed,
        'ordered_by' => $this->doctor->id,
        'is_active' => true,
    ]);

    $other = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );

    $page = contextWorkspacePage();
    $page->selectPatient($this->patient->id);

    expect($page->diagnosisFormData['diagnoses'][0]['description'])->toBe('Malaria');

    $page->selectPatient($other->id);

    expect($page->diagnosisFormData['diagnoses'])->toHaveCount(1)
        ->and($page->diagnosisFormData['diagnoses'][0]['description'])->toBeNull()
        ->and($page->diagnosisFormEncounterId)->toBeNull()
        ->and($page->writableEncounter())->toBeNull();
});

it('updates an edited diagnosis in place instead of inserting a new row', function (): void {
    $encounter = contextActiveEncounter($this->patient, $this->branch);

    $page = contextWorkspacePage();
    $page->selectPatient($this->patient->id);
    $page->diagnosisFormData = ['diagnoses' => [contextDiagnosisRow(['description' => 'Malaria'])]];
    $page->saveDiagnoses();

    $saved = EncounterDiagnosis::query()->where('encounter_id', $encounter->id)->sole();

    expect($saved->is_new_case)->toBeFalse()
        ->and($page->diagnosisFormData['diagnoses'][0]['encounter_diagnosis_id'])->toBe($saved->id);

    // The clinician changes their mind and marks it a new case.
    $page->diagnosisFormData['diagnoses'][0]['is_new_case'] = '1';
    $page->diagnosisFormData['diagnoses'][0]['certainty'] = DiagnosisCertainty::Confirmed->value;
    $page->saveDiagnoses();

    $rows = EncounterDiagnosis::query()->where('encounter_id', $encounter->id)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->id)->toBe($saved->id)
        ->and($rows[0]->is_active)->toBeTrue()
        ->and($rows[0]->is_new_case)->toBeTrue()
        ->and($rows[0]->certainty)->toBe(DiagnosisCertainty::Confirmed);
});

it('keeps the ICD codes of a catalogue pick across repeated saves', function (): void {
    $encounter = contextActiveEncounter($this->patient, $this->branch);

    $code = DiagnosisCode::factory()->create([
        'code' => 'B54',
        'description' => 'Unspecified malaria',
        'source' => 'icd10',
    ]);

    $page = contextWorkspacePage();
    $page->selectPatient($this->patient->id);
    $page->diagnosisFormData = ['diagnoses' => [contextDiagnosisRow(['code_search' => 'local:'.$code->id])]];
    $page->saveDiagnoses();

    $first = EncounterDiagnosis::query()->where('encounter_id', $encounter->id)->sole();

    expect($first->diagnosis_code_id)->toBe($code->id)
        ->and($first->icd_code)->toBe('B54')
        ->and($first->icd10_code)->toBe('B54')
        ->and($first->description)->toBe('Unspecified malaria');

    // Re-save the form exactly as it was reloaded (hidden code fields included).
    $page->saveDiagnoses();

    $again = EncounterDiagnosis::query()->where('encounter_id', $encounter->id)->get();

    expect($again)->toHaveCount(1)
        ->and($again[0]->id)->toBe($first->id)
        ->and($again[0]->icd_code)->toBe('B54')
        ->and($again[0]->icd10_code)->toBe('B54')
        ->and($again[0]->diagnosis_code_id)->toBe($code->id);
});

it('ignores a row id that belongs to another encounter', function (): void {
    $encounter = contextActiveEncounter($this->patient, $this->branch);

    $otherEncounter = Encounter::factory()
        ->forPatient($this->patient)
        ->finished()
        ->create(['branch_id' => $this->branch->id]);

    $foreign = EncounterDiagnosis::create([
        'encounter_id' => $otherEncounter->id,
        'patient_id' => $this->patient->id,
        'description' => 'Old typhoid',
        'type' => DiagnosisType::Primary,
        'certainty' => DiagnosisCertainty::Confirmed,
        'ordered_by' => $this->doctor->id,
        'is_active' => true,
    ]);

    $page = contextWorkspacePage();
    $page->selectPatient($this->patient->id);
    $page->diagnosisFormData = ['diagnoses' => [contextDiagnosisRow([
        'encounter_diagnosis_id' => $foreign->id,
        'description' => 'Hijack attempt',
    ])]];
    $page->saveDiagnoses();

    expect($foreign->fresh()->description)->toBe('Old typhoid')
        ->and($foreign->fresh()->is_active)->toBeTrue()
        ->and(EncounterDiagnosis::query()->where('encounter_id', $encounter->id)->where('is_active', true)->pluck('description')->all())
        ->toBe(['Hijack attempt']);
});

it('removes the last diagnosis when an empty set is saved', function (): void {
    $encounter = contextActiveEncounter($this->patient, $this->branch);

    EncounterDiagnosis::create([
        'encounter_id' => $encounter->id,
        'patient_id' => $this->patient->id,
        'description' => 'Malaria',
        'type' => DiagnosisType::Primary,
        'certainty' => DiagnosisCertainty::Confirmed,
        'ordered_by' => $this->doctor->id,
        'is_active' => true,
    ]);

    $page = contextWorkspacePage();
    $page->selectPatient($this->patient->id);

    expect($page->savedDiagnosisCount())->toBe(1);

    $page->diagnosisFormData = ['diagnoses' => [contextDiagnosisRow()]];
    $page->saveDiagnoses();

    expect(EncounterDiagnosis::query()->where('encounter_id', $encounter->id)->where('is_active', true)->count())->toBe(0)
        ->and(EncounterDiagnosis::query()->where('encounter_id', $encounter->id)->count())->toBe(1)
        ->and($page->savedDiagnosisCount())->toBe(0);
});

it('keeps unsaved diagnosis edits when vitals are saved', function (): void {
    contextActiveEncounter($this->patient, $this->branch);

    $page = contextWorkspacePage();
    $page->selectPatient($this->patient->id);
    $page->diagnosisFormData['diagnoses'][0]['description'] = 'Draft diagnosis';

    $page->vitalsData = [
        ...$page->vitalsData,
        'systolic_bp' => 120,
        'diastolic_bp' => 80,
    ];
    $page->saveVitals();

    expect(VitalSign::query()->where('patient_id', $this->patient->id)->count())->toBe(1)
        ->and($page->diagnosisFormData['diagnoses'][0]['description'])->toBe('Draft diagnosis');
});

it('defaults the vitals time to now on patient select and on entering the tab, without overwriting a typed time', function (): void {
    Carbon::setTestNow('2026-11-25 09:15:00');

    $page = contextWorkspacePage();
    $page->selectPatient($this->patient->id);

    expect($page->vitalsData['recorded_at'])->toBe('2026-11-25 09:15:00');

    Carbon::setTestNow('2026-11-25 09:40:00');
    $page->activateTab('vitals');

    // Still the page's own stamp, so it moves to the current time.
    expect($page->vitalsData['recorded_at'])->toBe('2026-11-25 09:40:00');

    $page->vitalsData['recorded_at'] = '2026-11-25 08:00:00';
    Carbon::setTestNow('2026-11-25 10:00:00');
    $page->activateTab('vitals');

    expect($page->vitalsData['recorded_at'])->toBe('2026-11-25 08:00:00');

    $page->vitalsData['recorded_at'] = null;
    $page->activateTab('vitals');

    expect($page->vitalsData['recorded_at'])->toBe('2026-11-25 10:00:00');

    Carbon::setTestNow();
});

it('saves a blank vitals time as the current time', function (): void {
    Carbon::setTestNow('2026-11-25 11:30:00');

    $page = contextWorkspacePage();
    $page->selectPatient($this->patient->id);
    $page->vitalsData = [
        ...$page->vitalsData,
        'recorded_at' => null,
        'systolic_bp' => 118,
        'diastolic_bp' => 76,
    ];
    $page->saveVitals();

    $vital = VitalSign::query()->where('patient_id', $this->patient->id)->sole();

    expect($vital->recorded_at->format('Y-m-d H:i:s'))->toBe('2026-11-25 11:30:00')
        ->and($page->vitalsData['recorded_at'])->toBe('2026-11-25 11:30:00');

    Carbon::setTestNow();
});

it('does not write clinical work to a finished encounter', function (): void {
    Encounter::factory()
        ->forPatient($this->patient)
        ->finished()
        ->create(['branch_id' => $this->branch->id]);

    $page = contextWorkspacePage();
    $page->selectPatient($this->patient->id);

    expect($page->currentEncounter)->not->toBeNull()
        ->and($page->writableEncounter())->toBeNull()
        ->and($page->diagnosisFormData['diagnoses'][0]['description'])->toBeNull();

    $page->consultationChiefComplaint = 'Headache';
    $page->consultationData['notes'] = '<p>Should not be stored</p>';
    $page->saveConsultation();

    $page->diagnosisFormData = ['diagnoses' => [contextDiagnosisRow(['description' => 'Should not be stored'])]];
    $page->saveDiagnoses();

    expect(ClinicalNote::query()->where('patient_id', $this->patient->id)->count())->toBe(0)
        ->and(EncounterDiagnosis::query()->where('patient_id', $this->patient->id)->count())->toBe(0);
});

it('reloads the diagnosis form and header actions when an encounter is created in the same request', function (): void {
    $page = Livewire::test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->assertSet('mode', 'patient')
        ->assertSet('diagnosisFormEncounterId', null);

    expect(contextHeaderActionVisible($page->instance(), 'diagnosis'))->toBeFalse();

    $page->set('diagnosisFormData.diagnoses.0.description', 'Typed before any visit existed')
        ->fillForm([
            'type' => EncounterType::OUTPATIENT->value,
            'coverage_type' => CoverageType::NONE->value,
            'chief_complaint' => 'Fever',
        ], 'encounterForm')
        ->call('createEncounter')
        ->assertHasNoErrors();

    $encounter = Encounter::query()->where('patient_id', $this->patient->id)->sole();

    $page->assertSet('diagnosisFormEncounterId', $encounter->id)
        ->assertSet('diagnosisFormData.diagnoses.0.description', null)
        ->assertSet('activeTab', 'vitals');

    expect($page->instance()->writableEncounter()?->id)->toBe($encounter->id)
        ->and(contextHeaderActionVisible($page->instance(), 'diagnosis'))->toBeTrue();
});

/**
 * Looks through the cached header actions (including action groups) for one by name.
 */
function contextHeaderActionVisible(ClinicalWorkspace $page, string $name): bool
{
    $flatten = function (array $actions) use (&$flatten): array {
        $flat = [];

        foreach ($actions as $action) {
            if ($action instanceof ActionGroup) {
                $flat = [...$flat, ...$flatten($action->getActions())];

                continue;
            }

            $flat[] = $action;
        }

        return $flat;
    };

    foreach ($flatten($page->getCachedHeaderActions()) as $action) {
        if ($action instanceof Action && $action->getName() === $name) {
            return $action->isVisible();
        }
    }

    return false;
}

it('announces saves to the patient-scoped widgets instead of relying on polling', function (): void {
    contextActiveEncounter($this->patient, $this->branch);

    Livewire::test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->set('vitalsData.systolic_bp', 121)
        ->set('vitalsData.diastolic_bp', 79)
        ->call('saveVitals')
        ->assertHasNoErrors()
        ->assertDispatched(ClinicalWorkspace::PATIENT_UPDATED_EVENT, patientId: $this->patient->id, scope: 'vitals');

    // The workspace mounts the vitals bar without a poll; other hosts keep a slow one.
    $silent = Livewire::test(PatientVitalsOverviewWidget::class, ['patientId' => $this->patient->id, 'pollingInterval' => null]);
    $polling = Livewire::test(PatientVitalsOverviewWidget::class, ['patientId' => $this->patient->id]);

    expect($silent->instance()->pollingInterval)->toBeNull()
        ->and($polling->instance()->pollingInterval)->toBe('60s');

    $polling->dispatch(ClinicalWorkspace::PATIENT_UPDATED_EVENT, patientId: $this->patient->id, scope: 'vitals')
        ->assertSee('121/79');
});

it('opens the tab named in the URL when the user may see it and falls back otherwise', function (): void {
    contextActiveEncounter($this->patient, $this->branch);

    Livewire::withQueryParams(['tab' => 'diagnosis'])
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->assertSet('activeTab', 'diagnosis');

    Livewire::withQueryParams(['tab' => 'not-a-tab'])
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->assertSet('activeTab', 'vitals');

    // A nurse has no diagnosis tab, so the deep link must not open it.
    $nurse = User::factory()->create(['branch_id' => $this->branch->id]);
    Role::findOrCreate('nurse', 'web');
    $nurse->assignRole('nurse');
    $nurse->givePermissionTo(['View ClinicalWorkspace', 'Create VitalSign', 'View VitalSign']);

    Livewire::actingAs($nurse)
        ->withQueryParams(['tab' => 'diagnosis'])
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->assertSet('activeTab', 'encounter')
        ->set('activeTab', 'diagnosis')
        ->assertSet('activeTab', 'encounter');
});

it('never retires a diagnosis the form did not load', function (): void {
    $encounter = contextActiveEncounter($this->patient, $this->branch);

    $page = contextWorkspacePage();
    $page->selectPatient($this->patient->id);
    $page->diagnosisFormData['diagnoses'][0]['description'] = 'Malaria';
    $page->saveDiagnoses();

    // Recorded by a colleague (or the header menu) after this form was loaded.
    EncounterDiagnosis::create([
        'encounter_id' => $encounter->id,
        'patient_id' => $this->patient->id,
        'description' => 'Anaemia',
        'type' => DiagnosisType::Secondary,
        'certainty' => DiagnosisCertainty::Confirmed,
        'ordered_by' => $this->doctor->id,
        'is_active' => true,
    ]);

    $page->saveDiagnoses();

    expect(EncounterDiagnosis::query()->where('encounter_id', $encounter->id)->where('is_active', true)->pluck('description')->sort()->values()->all())
        ->toBe(['Anaemia', 'Malaria'])
        ->and($page->savedDiagnosisCount())->toBe(2);
});

it('refreshes the page after a header menu save', function (): void {
    contextActiveEncounter($this->patient, $this->branch);

    Livewire::test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->callAction('diagnosis', data: [
            'description' => 'From the header menu',
            'type' => DiagnosisType::Primary->value,
            'is_new_case' => '0',
            'certainty' => DiagnosisCertainty::Provisional->value,
        ])
        ->assertHasNoErrors()
        ->assertSet('diagnosisFormData.diagnoses.0.description', 'From the header menu')
        ->assertDispatched(ClinicalWorkspace::PATIENT_UPDATED_EVENT);
});

it('keeps a half-typed encounter when something else is saved', function (): void {
    $page = contextWorkspacePage();
    $page->selectPatient($this->patient->id);
    $page->encounterFormData['chief_complaint'] = 'Half typed';
    $page->vitalsData = [...$page->vitalsData, 'systolic_bp' => 120, 'diastolic_bp' => 80];
    $page->saveVitals();

    expect($page->encounterFormData['chief_complaint'])->toBe('Half typed');
});

it('stamps the first dose time and lets default-open history cards close', function (): void {
    Carbon::setTestNow('2026-11-25 12:00:00');

    $page = contextWorkspacePage();
    $page->selectPatient($this->patient->id);
    $page->updatedActiveTab('medication');

    expect($page->medicationData['items'][0]['start_time'])->toBe('2026-11-25 12:00:00')
        ->and($page->isHistoryExpanded('a', 0))->toBeTrue();

    $page->toggleHistoryDetails('a', 0);

    expect($page->isHistoryExpanded('a', 0))->toBeFalse();

    Carbon::setTestNow();
});

it('reports ghost diagnoses by default and deletes only exact twins with --force', function (): void {
    $encounter = contextActiveEncounter($this->patient, $this->branch);
    $make = fn (string $description, bool $active): EncounterDiagnosis => EncounterDiagnosis::create([
        'encounter_id' => $encounter->id,
        'patient_id' => $this->patient->id,
        'description' => $description,
        'type' => DiagnosisType::Primary,
        'certainty' => DiagnosisCertainty::Confirmed,
        'ordered_by' => $this->doctor->id,
        'is_active' => $active,
    ]);

    $kept = $make('Malaria', true);
    $ghost = $make('Malaria', false);
    $removed = $make('Deliberately removed', false);

    $this->artisan('clinical:prune-diagnosis-ghosts')->assertSuccessful();
    expect($ghost->fresh())->not->toBeNull();

    $this->artisan('clinical:prune-diagnosis-ghosts', ['--force' => true])->assertSuccessful();
    expect($ghost->fresh())->toBeNull()
        ->and($kept->fresh())->not->toBeNull()
        ->and($removed->fresh())->not->toBeNull();
});
