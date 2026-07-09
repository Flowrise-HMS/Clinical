<?php

namespace Modules\Clinical\Tests\Unit;

use App\Models\User;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Classes\Services\VitalSignService;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Filament\Clusters\Workspace\Concerns\ManagesWorkspacePatient;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Models\Patient;
use Nnjeim\World\Models\Country;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClinicalWorkspacePatientManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected User $nurse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff']);
        $this->ensureDefaultCountryExists();

        $this->branch = Branch::factory()->default()->create();
        $this->nurse = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->grantPermissions($this->nurse, ['Create Patient', 'View Patient', 'Update Patient']);
        Role::findOrCreate('nurse', 'web');
        $this->nurse->assignRole('nurse');
    }

    public function test_register_patient_enters_post_registration_encounter_flow(): void
    {
        Auth::login($this->nurse);
        $workspace = $this->makeHarness();

        $workspace->startRegistration();
        $workspace->registerFormData['first_name'] = 'Walk';
        $workspace->registerFormData['last_name'] = 'In';
        $workspace->registerFormData['date_of_birth'] = '1995-06-15';
        $workspace->registerFormData['gender'] = Gender::MALE->value;
        $workspace->registerFormData['phone'] = '0244111222';

        $workspace->registerPatient();

        $this->assertSame('patient', $workspace->mode);
        $this->assertTrue($workspace->postRegistrationFlow);
        $this->assertSame('encounter', $workspace->activeTab);
        $this->assertNotNull($workspace->patientId);
        $this->assertNotNull($workspace->currentPatient);
    }

    public function test_create_encounter_after_registration_advances_to_vitals(): void
    {
        Auth::login($this->nurse);
        $workspace = $this->makeHarness();

        $patient = Patient::factory()->create(['branch_id' => $this->branch->id]);
        $workspace->selectPatient($patient->id, fromRegistration: true);

        $workspace->encounterFormData = [
            'coverage_type' => 'none',
            'chief_complaint' => 'Fever',
        ];
        $workspace->createEncounter();

        $this->assertSame('vitals', $workspace->activeTab);
        $this->assertDatabaseHas('encounters', [
            'patient_id' => $patient->id,
            'chief_complaint' => 'Fever',
        ]);
    }

    public function test_save_vitals_clears_post_registration_flow(): void
    {
        Auth::login($this->nurse);
        $workspace = $this->makeHarness();

        $patient = Patient::factory()->create(['branch_id' => $this->branch->id]);
        Encounter::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
        ]);

        $workspace->selectPatient($patient->id, fromRegistration: true);
        $workspace->vitalsData = [
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'heart_rate' => 72,
        ];
        $workspace->saveVitals();

        $this->assertFalse($workspace->postRegistrationFlow);
    }

    public function test_save_patient_details_updates_record(): void
    {
        Auth::login($this->nurse);
        $workspace = $this->makeHarness();

        $patient = Patient::factory()->create([
            'branch_id' => $this->branch->id,
            'first_name' => 'Original',
        ]);

        $workspace->selectPatient($patient->id);
        $workspace->patientFormData['first_name'] = 'Updated';
        $workspace->savePatientDetails();

        $this->assertSame('Updated', $patient->fresh()->first_name);
    }

    public function test_start_registration_requires_create_permission(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id]);
        Auth::login($user);
        $workspace = $this->makeHarness();

        $this->expectException(AuthorizationException::class);
        $workspace->startRegistration();
    }

    protected function makeHarness(): ClinicalWorkspacePatientHarness
    {
        $harness = new ClinicalWorkspacePatientHarness;
        $harness->boot();

        return $harness;
    }

    protected function grantPermissions(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }
    }

    protected function ensureDefaultCountryExists(): void
    {
        $countryCode = config('core.default_country_code', 'GH');

        Country::query()->firstOrCreate(
            ['iso2' => $countryCode],
            [
                'name' => 'Ghana',
                'status' => 1,
                'phone_code' => '233',
                'iso3' => 'GHA',
                'region' => 'Africa',
                'subregion' => 'Western Africa',
            ],
        );
    }
}

class ClinicalWorkspacePatientHarness extends Component implements HasSchemas
{
    use InteractsWithSchemas, ManagesWorkspacePatient;

    public ?string $patientId = null;

    public string $mode = 'home';

    public string $searchTerm = '';

    public string $activeTab = '';

    public ?Patient $currentPatient = null;

    public ?Encounter $currentEncounter = null;

    public array $encounterFormData = [];

    public array $vitalsData = [];

    public function boot(): void
    {
        foreach ($this->registerPatientManagementForms() as $name => $schema) {
            $this->cacheSchema($name, $schema);
        }
    }

    public function selectPatient(string $id, bool $fromRegistration = false): void
    {
        $this->patientId = $id;
        $this->mode = 'patient';
        $this->loadPatientContext();

        if ($fromRegistration) {
            $this->postRegistrationFlow = true;
            $this->activeTab = $this->getPostRegistrationTab();
        } else {
            $this->postRegistrationFlow = false;
            $this->activeTab = 'vitals';
        }

        $this->fillPatientFormDataFromCurrentPatient();
    }

    protected function loadPatientContext(): void
    {
        $this->currentPatient = Patient::with(['allergies', 'activeEncounter', 'latestEncounter'])
            ->find($this->patientId);

        $this->currentEncounter = $this->currentPatient?->activeEncounter
            ?? $this->currentPatient?->latestEncounter;
    }

    protected function getUserRoleKey(): string
    {
        return 'nurse';
    }

    public function createEncounter(): void
    {
        if (! $this->currentPatient) {
            return;
        }

        $encounter = app(EncounterService::class)->createForPatient(
            patient: $this->currentPatient,
            type: EncounterType::OUTPATIENT,
            chiefComplaint: $this->encounterFormData['chief_complaint'] ?? null,
            priority: EncounterPriority::ROUTINE,
        );

        if ($coverage = $this->encounterFormData['coverage_type'] ?? null) {
            $encounter->update(['coverage_type' => $coverage]);
        }

        $this->currentEncounter = $encounter->fresh();
        $this->encounterFormData = [];
        $this->activeTab = 'vitals';
    }

    public function saveVitals(): void
    {
        if (! $this->currentPatient) {
            return;
        }

        app(VitalSignService::class)->record(
            $this->currentPatient,
            $this->vitalsData,
            $this->currentEncounter?->id,
        );

        $this->vitalsData = [];

        if ($this->postRegistrationFlow) {
            $this->postRegistrationFlow = false;
        }
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
