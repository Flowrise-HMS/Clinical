<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Enums\AllergenType;
use Modules\Clinical\Enums\AllergySeverity;
use Modules\Clinical\Enums\AllergyVerificationStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\OnsetType;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Models\Allergy;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The workspace quick forms feed their state straight into services, so each save must read
 * its state back through the schema. Without that, a blank form reaches the database.
 */
class ClinicalWorkspaceFormValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected User $clinician;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff']);

        $this->branch = Branch::factory()->default()->create();
        $this->clinician = User::factory()->create(['branch_id' => $this->branch->id]);
        Role::findOrCreate('doctor', 'web');
        $this->clinician->assignRole('doctor');

        foreach ([
            'Create Allergy',
            'Create ServiceRequest',
            'Create ClinicalNote',
            'Create Encounter',
            'Update Encounter',
            'View Encounter',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
            $this->clinician->givePermissionTo($permission);
        }

        $this->patient = Patient::withoutEvents(
            fn () => Patient::factory()->create(['branch_id' => $this->branch->id])
        );
    }

    public function test_save_allergy_rejects_an_empty_submission(): void
    {
        $this->actingAs($this->clinician);

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->allergyData = [];

        try {
            $page->saveAllergy();
            $this->fail('An empty allergy should not be accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('allergyData.allergen_name', $exception->errors());
        }

        $this->assertSame(0, Allergy::query()->where('patient_id', $this->patient->id)->count());
    }

    public function test_save_allergy_persists_a_complete_submission(): void
    {
        $this->actingAs($this->clinician);

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->allergyData = [
            'allergen_type' => AllergenType::MEDICATION->value,
            'allergen_name' => 'Penicillin',
            'severity' => AllergySeverity::SEVERE->value,
            'verification_status' => AllergyVerificationStatus::VERIFIED->value,
            'onset_type' => OnsetType::ACUTE->value,
        ];

        $page->saveAllergy();

        $this->assertDatabaseHas('allergies', [
            'patient_id' => $this->patient->id,
            'allergen' => 'Penicillin',
        ]);
    }

    public function test_save_service_request_rejects_an_empty_submission(): void
    {
        $this->actingAs($this->clinician);

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->serviceRequestData = [];

        try {
            $page->saveServiceRequest();
            $this->fail('An empty service request should not be accepted.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $this->assertSame(0, ServiceRequest::query()->where('patient_id', $this->patient->id)->count());
    }

    public function test_save_referral_requires_a_destination(): void
    {
        $this->actingAs($this->clinician);

        app(EncounterService::class)->createForPatient(
            patient: $this->patient,
            type: EncounterType::OUTPATIENT,
            createdBy: $this->clinician->id,
        );

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->referralData = ['notes' => null];

        try {
            $page->saveReferral();
            $this->fail('A referral without a destination should not be accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('referralData.destination', $exception->errors());
        }

        $this->assertSame(0, ClinicalNote::query()->where('patient_id', $this->patient->id)->count());
    }

    protected function makeWorkspacePage(): ClinicalWorkspace
    {
        $page = app(ClinicalWorkspace::class);
        $page->boot();

        return $page;
    }
}
