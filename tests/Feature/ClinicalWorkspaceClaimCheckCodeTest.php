<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Enums\CoverageType;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClinicalWorkspaceClaimCheckCodeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Clinical']);

        $this->branch = Branch::factory()->default()->create();
        $this->patient = Patient::withoutEvents(
            fn () => Patient::factory()->create(['branch_id' => $this->branch->id])
        );

        Permission::findOrCreate('View ClinicalWorkspace', 'web');
        $this->user = User::factory()->create()->givePermissionTo('View ClinicalWorkspace');
    }

    public function test_claim_check_code_field_only_visible_for_nhis_coverage(): void
    {
        Livewire::actingAs($this->user)
            ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
            ->assertFormFieldHidden('claim_check_code', 'encounterForm')
            ->set('encounterFormData.coverage_type', CoverageType::NHIS->value)
            ->assertFormFieldVisible('claim_check_code', 'encounterForm')
            ->set('encounterFormData.coverage_type', CoverageType::NONE->value)
            ->assertFormFieldHidden('claim_check_code', 'encounterForm');
    }

    public function test_claim_check_code_is_persisted_when_creating_an_nhis_encounter(): void
    {
        Livewire::actingAs($this->user)
            ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
            ->set('encounterFormData.coverage_type', CoverageType::NHIS->value)
            ->set('encounterFormData.claim_check_code', 'VALID1234567')
            ->call('createEncounter');

        $encounter = Encounter::where('patient_id', $this->patient->id)->first();

        $this->assertNotNull($encounter);
        $this->assertSame(CoverageType::NHIS->value, $encounter->coverage_type?->value);
        $this->assertSame('VALID1234567', $encounter->claim_check_code);
    }
}
