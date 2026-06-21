<?php

namespace Modules\Clinical\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class ClinicalEncounterModelTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical']);
    }

    public function test_encounter_factory_creates_encounter(): void
    {
        $encounter = Encounter::factory()->create();
        $this->assertTrue($encounter->exists);
        $this->assertNotNull($encounter->id);
    }

    public function test_encounter_belongs_to_branch(): void
    {
        $branch = Branch::factory()->create();
        $encounter = Encounter::factory()->create(['branch_id' => $branch->id]);

        $this->assertEquals($branch->id, $encounter->branch->id);
    }

    public function test_encounter_belongs_to_patient(): void
    {
        $patient = Patient::factory()->create();
        $encounter = Encounter::factory()->create(['patient_id' => $patient->id]);

        $this->assertEquals($patient->id, $encounter->patient->id);
    }
}
