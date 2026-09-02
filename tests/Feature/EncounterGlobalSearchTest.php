<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\EncounterResource;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EncounterGlobalSearchTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Clinical']);

        Permission::findOrCreate('ViewAny Encounter', 'web');
        Permission::findOrCreate('View Encounter', 'web');
        $this->actingAs(User::factory()->create()->givePermissionTo('ViewAny Encounter', 'View Encounter'));
    }

    protected function createEncounterForSearch(): void
    {
        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create([
            'branch_id' => $branch->id,
            'mrn' => 'MRN-GS-777',
            'title' => null,
            'first_name' => 'Zainab',
            'middle_name' => null,
            'last_name' => 'Fuseini',
        ]));

        EncounterFactory::new()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'encounter_number' => 'ENC-GS-0001',
        ]);
    }

    public function test_encounters_are_found_by_encounter_number(): void
    {
        $this->createEncounterForSearch();

        $results = EncounterResource::getGlobalSearchResults('ENC-GS-0001');

        $this->assertCount(1, $results);
        $this->assertSame('ENC-GS-0001', $results->first()->title);
        $this->assertSame('Zainab Fuseini', $results->first()->details['Patient'] ?? null);
    }

    public function test_encounters_are_found_by_patient_name_and_mrn(): void
    {
        $this->createEncounterForSearch();

        $this->assertCount(1, EncounterResource::getGlobalSearchResults('Fuseini'));
        $this->assertCount(1, EncounterResource::getGlobalSearchResults('MRN-GS-777'));
        $this->assertCount(0, EncounterResource::getGlobalSearchResults('no-such-patient'));
    }
}
