<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClinicalWorkspaceVitalsValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected User $nurse;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff']);

        $this->branch = Branch::factory()->default()->create();
        $this->nurse = User::factory()->create(['branch_id' => $this->branch->id]);
        Role::findOrCreate('nurse', 'web');
        $this->nurse->assignRole('nurse');

        foreach (['Create VitalSign', 'View VitalSign'] as $permission) {
            Permission::findOrCreate($permission, 'web');
            $this->nurse->givePermissionTo($permission);
        }

        $this->patient = Patient::withoutEvents(
            fn () => Patient::factory()->create(['branch_id' => $this->branch->id])
        );
    }

    public function test_save_vitals_rejects_an_empty_submission(): void
    {
        $this->actingAs($this->nurse);

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->vitalsData = [];

        try {
            $page->saveVitals();
            $this->fail('Empty vitals should not be accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('vitalsData.systolic_bp', $exception->errors());
            $this->assertArrayHasKey('vitalsData.diastolic_bp', $exception->errors());
        }

        $this->assertDatabaseMissing('vital_signs', ['patient_id' => $this->patient->id]);
    }

    public function test_save_vitals_rejects_a_partial_blood_pressure_reading(): void
    {
        $this->actingAs($this->nurse);

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->vitalsData = [
            'systolic_bp' => 120,
            'heart_rate' => 72,
        ];

        try {
            $page->saveVitals();
            $this->fail('A blood pressure reading without a diastolic value should not be accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('vitalsData.diastolic_bp', $exception->errors());
        }

        $this->assertDatabaseMissing('vital_signs', ['patient_id' => $this->patient->id]);
    }

    public function test_save_vitals_persists_a_complete_blood_pressure_reading(): void
    {
        $this->actingAs($this->nurse);

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->vitalsData = [
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'heart_rate' => 72,
        ];

        $page->saveVitals();

        $vitalSign = VitalSign::query()
            ->where('patient_id', $this->patient->id)
            ->firstOrFail();

        $this->assertSame(120, (int) $vitalSign->systolic_bp);
        $this->assertSame(80, (int) $vitalSign->diastolic_bp);
    }

    protected function makeWorkspacePage(): ClinicalWorkspace
    {
        $page = app(ClinicalWorkspace::class);
        $page->boot();

        return $page;
    }
}
