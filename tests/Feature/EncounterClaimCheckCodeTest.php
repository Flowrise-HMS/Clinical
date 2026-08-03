<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages\CreateEncounter;
use Modules\Core\Enums\CoverageType;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EncounterClaimCheckCodeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Clinical']);

        Permission::findOrCreate('Create Encounter', 'web');
        Permission::findOrCreate('View Encounter', 'web');
        Permission::findOrCreate('ViewAny Encounter', 'web');
        $this->user = User::factory()->create()
            ->givePermissionTo('Create Encounter', 'View Encounter', 'ViewAny Encounter');
    }

    public function test_claim_check_code_field_only_visible_for_nhis_coverage_on_encounter_form(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateEncounter::class)
            ->assertFormFieldHidden('claim_check_code')
            ->fillForm(['coverage_type' => CoverageType::NHIS->value])
            ->assertFormFieldVisible('claim_check_code')
            ->fillForm(['coverage_type' => CoverageType::NONE->value])
            ->assertFormFieldHidden('claim_check_code');
    }
}
