<?php

namespace Modules\Clinical\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Models\Allergy;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\DiagnosisCode;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\EncounterParticipant;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\MedicationDoseReminderLog;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Clinical\Models\Task;
use Modules\Clinical\Models\VitalSign;
use Tests\TestCase;

class ClinicalFactorySmokeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical']);
    }

    public function test_diagnosis_code_factory(): void
    {
        $code = DiagnosisCode::factory()->create();
        $this->assertTrue($code->exists);
        $this->assertNotNull($code->id);
    }

    public function test_encounter_factory(): void
    {
        $encounter = Encounter::factory()->create();
        $this->assertTrue($encounter->exists);
        $this->assertNotNull($encounter->id);
    }

    public function test_encounter_diagnosis_factory(): void
    {
        $encounterDiagnosis = EncounterDiagnosis::factory()->create();
        $this->assertTrue($encounterDiagnosis->exists);
        $this->assertNotNull($encounterDiagnosis->id);
    }

    public function test_encounter_participant_factory(): void
    {
        $participant = EncounterParticipant::factory()->create();
        $this->assertTrue($participant->exists);
        $this->assertNotNull($participant->id);
    }

    public function test_allergy_factory(): void
    {
        $allergy = Allergy::factory()->create();
        $this->assertTrue($allergy->exists);
        $this->assertNotNull($allergy->id);
    }

    public function test_clinical_note_factory(): void
    {
        $note = ClinicalNote::factory()->create();
        $this->assertTrue($note->exists);
        $this->assertNotNull($note->id);
    }

    public function test_vital_sign_factory(): void
    {
        $vital = VitalSign::factory()->create();
        $this->assertTrue($vital->exists);
        $this->assertNotNull($vital->id);
    }

    public function test_service_request_factory(): void
    {
        $request = ServiceRequest::factory()->create();
        $this->assertTrue($request->exists);
        $this->assertNotNull($request->id);
    }

    public function test_request_item_factory(): void
    {
        $item = RequestItem::factory()->create();
        $this->assertTrue($item->exists);
        $this->assertNotNull($item->id);
    }

    public function test_task_factory(): void
    {
        $task = Task::factory()->create();
        $this->assertTrue($task->exists);
        $this->assertNotNull($task->id);
    }

    public function test_medication_administration_factory(): void
    {
        $admin = MedicationAdministration::factory()->create();
        $this->assertTrue($admin->exists);
        $this->assertNotNull($admin->id);
    }

    public function test_medication_dose_reminder_log_factory(): void
    {
        $log = MedicationDoseReminderLog::factory()->create();
        $this->assertTrue($log->exists);
        $this->assertNotNull($log->id);
    }
}
