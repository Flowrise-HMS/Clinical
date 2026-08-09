<?php

use App\Models\User;
use Modules\Clinical\Classes\Services\DiagnosisService;
use Modules\Clinical\Enums\DiagnosisCertainty;
use Modules\Clinical\Enums\DiagnosisType;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Models\DiagnosisCode;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
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

function storeBothDoctor(Branch $branch): User
{
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $role = Role::findOrCreate('doctor', 'web');
    $user->assignRole($role);

    foreach (['Create EncounterDiagnosis', 'Create Encounter', 'Update Encounter', 'View Encounter'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

function storeBothWorkspace(Patient $patient, Branch $branch): array
{
    $encounter = Encounter::factory()
        ->forPatient($patient)
        ->active()
        ->create(['branch_id' => $branch->id]);

    $page = app(ClinicalWorkspace::class);
    $page->boot();
    $page->selectPatient($patient->id);

    return [$page, $encounter];
}

function whoOnlyRow(): array
{
    return [
        'code_search' => 'who:123',
        'icd_code' => '1A00',
        'icd_entity_id' => '123',
        'icd_uri' => 'https://id.who.int/icd/entity/123',
        'description' => 'Cholera',
        'type' => DiagnosisType::Primary->value,
        'is_new_case' => '0',
        'certainty' => DiagnosisCertainty::Provisional->value,
    ];
}

it('persists both who icd-11 and local icd-10 codes on the same diagnosis row', function (): void {
    $this->actingAs(storeBothDoctor($this->branch));
    [$page, $encounter] = storeBothWorkspace($this->patient, $this->branch);

    $row = whoOnlyRow();
    $row['icd10_code'] = 'A00.0';
    $page->diagnosisFormData = ['diagnoses' => [$row]];
    $page->saveDiagnoses();

    $dx = EncounterDiagnosis::where('encounter_id', $encounter->id)->first();

    expect($dx)->not->toBeNull()
        ->and($dx->icd_code)->toBe('1A00')
        ->and($dx->icd10_code)->toBe('A00.0')
        ->and($dx->icd_entity_id)->toBe('123')
        ->and($dx->icd_uri)->toBe('https://id.who.int/icd/entity/123');

    $existing = app(DiagnosisService::class)->getForEncounter($encounter->id);

    expect($existing['diagnoses'][0]['icd10_code'])->toBe('A00.0')
        ->and($existing['diagnoses'][0]['icd_code'])->toBe('1A00');
});

it('auto-caches a who-only selection into the local catalogue on save', function (): void {
    $this->actingAs(storeBothDoctor($this->branch));
    [$page, $encounter] = storeBothWorkspace($this->patient, $this->branch);

    $page->diagnosisFormData = ['diagnoses' => [whoOnlyRow()]];
    $page->saveDiagnoses();

    $cached = DiagnosisCode::where('source', 'who')->where('icd_entity_id', '123')->first();

    expect($cached)->not->toBeNull()
        ->and($cached->code)->toBe('1A00')
        ->and($cached->description)->toBe('Cholera');

    $dx = EncounterDiagnosis::where('encounter_id', $encounter->id)->first();

    expect($dx->diagnosis_code_id)->toBe($cached->id);
});

it('does not duplicate the cached who row on repeated saves', function (): void {
    $this->actingAs(storeBothDoctor($this->branch));
    [$page, $encounter] = storeBothWorkspace($this->patient, $this->branch);

    $page->diagnosisFormData = ['diagnoses' => [whoOnlyRow()]];
    $page->saveDiagnoses();
    $page->saveDiagnoses();

    expect(DiagnosisCode::where('source', 'who')->where('icd_entity_id', '123')->count())->toBe(1);
});
