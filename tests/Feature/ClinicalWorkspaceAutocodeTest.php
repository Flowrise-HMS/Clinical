<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Clinical\Enums\DiagnosisType;
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

    config([
        'clinical.icd.client_id' => 'test-client',
        'clinical.icd.client_secret' => 'test-secret',
        'clinical.icd.token_url' => 'https://icdaccessmanagement.who.int/connect/token',
        'clinical.icd.base_url' => 'https://id.who.int',
        'clinical.icd.release_id' => '2026-01',
        'clinical.icd.linearization' => 'mms',
    ]);

    Http::fake([
        'icdaccessmanagement.who.int/*' => Http::response([
            'access_token' => 'token',
            'expires_in' => 3600,
        ]),
        'id.who.int/*/mms/autocode*' => Http::response([
            'matchingText' => 'Cholera',
            'theCode' => '1A00',
            'foundationURI' => 'http://id.who.int/icd/entity/123',
            'linearizationURI' => 'http://id.who.int/icd/release/11/2026-01/mms/1A00',
        ]),
    ]);
});

function autocodeDoctor(Branch $branch): User
{
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $role = Role::findOrCreate('doctor', 'web');
    $user->assignRole($role);

    foreach (['Create EncounterDiagnosis', 'Create Encounter', 'View Encounter'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

function workspaceWithEncounter(Patient $patient, Branch $branch, User $user): ClinicalWorkspace
{
    Encounter::factory()
        ->forPatient($patient)
        ->active()
        ->create(['branch_id' => $branch->id]);

    $page = app(ClinicalWorkspace::class);
    $page->boot();
    $page->selectPatient($patient->id);

    return $page;
}

it('fills the first uncoded diagnosis row with the suggested code', function (): void {
    $this->actingAs(autocodeDoctor($this->branch));
    $page = workspaceWithEncounter($this->patient, $this->branch, auth()->user());

    $page->autocodeText = 'Cholera';
    $page->suggestIcdCode();

    expect($page->autocodeSuggestion['code'])->toBe('1A00');

    $page->diagnosisFormData = [
        'diagnoses' => [
            [
                'description' => 'Severe watery diarrhoea',
                'type' => DiagnosisType::Primary->value,
                'is_new_case' => '0',
                'certainty' => 'provisional',
            ],
        ],
    ];

    $page->applyAutocodeToDiagnosis();

    $row = $page->diagnosisFormData['diagnoses'][0];

    expect($row['icd_code'])->toBe('1A00')
        ->and($row['icd_entity_id'])->toBe('123')
        ->and($row['icd_uri'])->toBe('https://id.who.int/icd/release/11/2026-01/mms/1A00')
        ->and($row['description'])->toBe('Severe watery diarrhoea')
        ->and($page->autocodeSuggestion)->toBeNull();
});

it('appends a new diagnosis row when no uncoded row exists', function (): void {
    $this->actingAs(autocodeDoctor($this->branch));
    $page = workspaceWithEncounter($this->patient, $this->branch, auth()->user());

    $page->diagnosisFormData = [
        'diagnoses' => [
            [
                'description' => 'Malaria',
                'code_search' => 'who:999',
                'icd_code' => '1B00',
                'icd_entity_id' => '999',
            ],
        ],
    ];

    $page->autocodeText = 'Cholera';
    $page->suggestIcdCode();
    $page->applyAutocodeToDiagnosis();

    $rows = $page->diagnosisFormData['diagnoses'];

    expect($rows)->toHaveCount(2)
        ->and($rows[1]['icd_code'])->toBe('1A00')
        ->and($rows[1]['description'])->toBe('Cholera');
});

it('does nothing when there is no suggestion', function (): void {
    $this->actingAs(autocodeDoctor($this->branch));
    $page = workspaceWithEncounter($this->patient, $this->branch, auth()->user());

    $page->diagnosisFormData = ['diagnoses' => []];

    $page->applyAutocodeToDiagnosis();

    expect($page->diagnosisFormData['diagnoses'])->toBeEmpty();
});
