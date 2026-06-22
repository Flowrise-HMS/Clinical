<?php

namespace Modules\Clinical\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Enums\AllergenType;
use Modules\Clinical\Enums\AllergySeverity;
use Modules\Clinical\Enums\AllergyVerificationStatus;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\OnsetType;
use Modules\Clinical\Enums\ParticipantRole;
use Modules\Clinical\Enums\ParticipantStatus;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Enums\RequestPriority;
use Modules\Clinical\Enums\TaskOutcome;
use Modules\Clinical\Enums\TaskStatus;
use Modules\Clinical\Models\Allergy;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterParticipant;
use Modules\Clinical\Models\Task;
use Tests\TestCase;

class EdgeCaseTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical']);
    }

    // ─── EncounterStatus state machine ──────────────────────────────────────

    public function test_encounter_status_default(): void
    {
        $this->assertSame(EncounterStatus::PLANNED, EncounterStatus::default());
    }

    public function test_encounter_status_values(): void
    {
        $values = EncounterStatus::values();
        $this->assertCount(7, $values);
        $this->assertContains('planned', $values);
        $this->assertContains('finished', $values);
        $this->assertContains('cancelled', $values);
    }

    public function test_encounter_status_allowed_transitions_from_planned(): void
    {
        $this->assertTrue(EncounterStatus::PLANNED->canTransitionTo(EncounterStatus::ARRIVED));
        $this->assertTrue(EncounterStatus::PLANNED->canTransitionTo(EncounterStatus::CANCELLED));
        $this->assertFalse(EncounterStatus::PLANNED->canTransitionTo(EncounterStatus::TRIAGED));
        $this->assertFalse(EncounterStatus::PLANNED->canTransitionTo(EncounterStatus::FINISHED));
    }

    public function test_encounter_status_allowed_transitions_from_arrived(): void
    {
        $this->assertTrue(EncounterStatus::ARRIVED->canTransitionTo(EncounterStatus::TRIAGED));
        $this->assertTrue(EncounterStatus::ARRIVED->canTransitionTo(EncounterStatus::CANCELLED));
        $this->assertFalse(EncounterStatus::ARRIVED->canTransitionTo(EncounterStatus::PLANNED));
        $this->assertFalse(EncounterStatus::ARRIVED->canTransitionTo(EncounterStatus::FINISHED));
    }

    public function test_encounter_status_allowed_transitions_from_triaged(): void
    {
        $this->assertTrue(EncounterStatus::TRIAGED->canTransitionTo(EncounterStatus::IN_PROGRESS));
        $this->assertTrue(EncounterStatus::TRIAGED->canTransitionTo(EncounterStatus::CANCELLED));
        $this->assertFalse(EncounterStatus::TRIAGED->canTransitionTo(EncounterStatus::ARRIVED));
    }

    public function test_encounter_status_allowed_transitions_from_in_progress(): void
    {
        $this->assertTrue(EncounterStatus::IN_PROGRESS->canTransitionTo(EncounterStatus::FINISHED));
        $this->assertTrue(EncounterStatus::IN_PROGRESS->canTransitionTo(EncounterStatus::ON_LEAVE));
        $this->assertTrue(EncounterStatus::IN_PROGRESS->canTransitionTo(EncounterStatus::CANCELLED));
        $this->assertFalse(EncounterStatus::IN_PROGRESS->canTransitionTo(EncounterStatus::TRIAGED));
    }

    public function test_encounter_status_allowed_transitions_from_on_leave(): void
    {
        $this->assertTrue(EncounterStatus::ON_LEAVE->canTransitionTo(EncounterStatus::IN_PROGRESS));
        $this->assertTrue(EncounterStatus::ON_LEAVE->canTransitionTo(EncounterStatus::FINISHED));
        $this->assertTrue(EncounterStatus::ON_LEAVE->canTransitionTo(EncounterStatus::CANCELLED));
        $this->assertFalse(EncounterStatus::ON_LEAVE->canTransitionTo(EncounterStatus::TRIAGED));
    }

    public function test_encounter_status_terminal_states_cannot_transition(): void
    {
        $this->assertFalse(EncounterStatus::FINISHED->canTransitionTo(EncounterStatus::PLANNED));
        $this->assertFalse(EncounterStatus::FINISHED->canTransitionTo(EncounterStatus::IN_PROGRESS));
        $this->assertFalse(EncounterStatus::CANCELLED->canTransitionTo(EncounterStatus::PLANNED));
        $this->assertFalse(EncounterStatus::CANCELLED->canTransitionTo(EncounterStatus::ARRIVED));
    }

    public function test_encounter_status_is_active(): void
    {
        $this->assertTrue(EncounterStatus::ARRIVED->isActive());
        $this->assertTrue(EncounterStatus::TRIAGED->isActive());
        $this->assertTrue(EncounterStatus::IN_PROGRESS->isActive());
        $this->assertTrue(EncounterStatus::ON_LEAVE->isActive());
        $this->assertFalse(EncounterStatus::PLANNED->isActive());
        $this->assertFalse(EncounterStatus::FINISHED->isActive());
        $this->assertFalse(EncounterStatus::CANCELLED->isActive());
    }

    public function test_encounter_status_is_completed(): void
    {
        $this->assertTrue(EncounterStatus::FINISHED->isCompleted());
        $this->assertTrue(EncounterStatus::CANCELLED->isCompleted());
        $this->assertFalse(EncounterStatus::IN_PROGRESS->isCompleted());
    }

    // ─── EncounterType enum ─────────────────────────────────────────────────

    public function test_encounter_type_values(): void
    {
        $values = EncounterType::values();
        $this->assertCount(5, $values);
        $this->assertContains('inpatient', $values);
        $this->assertContains('outpatient', $values);
        $this->assertContains('emergency', $values);
        $this->assertContains('virtual', $values);
        $this->assertContains('home_visit', $values);
    }

    // ─── TaskStatus state machine ───────────────────────────────────────────

    public function test_task_status_default_and_values(): void
    {
        $this->assertSame(TaskStatus::PENDING, TaskStatus::default());
        $this->assertCount(4, TaskStatus::values());
    }

    public function test_task_status_is_terminal(): void
    {
        $this->assertTrue(TaskStatus::COMPLETED->isTerminal());
        $this->assertTrue(TaskStatus::CANCELLED->isTerminal());
        $this->assertFalse(TaskStatus::PENDING->isTerminal());
        $this->assertFalse(TaskStatus::IN_PROGRESS->isTerminal());
    }

    public function test_task_status_is_active(): void
    {
        $this->assertTrue(TaskStatus::IN_PROGRESS->isActive());
        $this->assertFalse(TaskStatus::PENDING->isActive());
        $this->assertFalse(TaskStatus::COMPLETED->isActive());
    }

    // ─── RequestItemStatus enum ─────────────────────────────────────────────

    public function test_request_item_status_default_and_values(): void
    {
        $this->assertSame(RequestItemStatus::PENDING, RequestItemStatus::default());
        $this->assertCount(4, RequestItemStatus::values());
    }

    public function test_request_item_status_is_terminal(): void
    {
        $this->assertTrue(RequestItemStatus::COMPLETED->isTerminal());
        $this->assertTrue(RequestItemStatus::CANCELLED->isTerminal());
        $this->assertFalse(RequestItemStatus::PENDING->isTerminal());
    }

    // ─── NoteStatus enum ────────────────────────────────────────────────────

    public function test_note_status_values(): void
    {
        $this->assertCount(3, NoteStatus::values());
        $this->assertContains('draft', NoteStatus::values());
        $this->assertContains('signed', NoteStatus::values());
        $this->assertContains('amended', NoteStatus::values());
    }

    public function test_note_status_can_be_edited(): void
    {
        $this->assertTrue(NoteStatus::DRAFT->canBeEdited());
        $this->assertFalse(NoteStatus::SIGNED->canBeEdited());
        $this->assertFalse(NoteStatus::AMENDED->canBeEdited());
    }

    // ─── AllergyVerificationStatus enum ─────────────────────────────────────

    public function test_allergy_verification_status_values(): void
    {
        $values = AllergyVerificationStatus::values();
        $this->assertCount(3, $values);
        $this->assertContains('unverified', $values);
        $this->assertContains('verified', $values);
        $this->assertContains('refuted', $values);
    }

    // ─── MedicationAdministrationStatus enum ────────────────────────────────

    public function test_medication_administration_status_values(): void
    {
        $values = MedicationAdministrationStatus::values();
        $this->assertCount(3, $values);
        $this->assertContains('given', $values);
        $this->assertContains('omitted', $values);
        $this->assertContains('refused', $values);
    }

    // ─── Encounter model ────────────────────────────────────────────────────

    public function test_encounter_has_uuid(): void
    {
        $encounter = Encounter::factory()->create();
        $this->assertNotNull($encounter->id);
        $this->assertTrue(strlen((string) $encounter->id) === 36);
    }

    public function test_encounter_auto_generates_number(): void
    {
        $encounter = Encounter::factory()->create();
        $this->assertNotNull($encounter->encounter_number);
        $this->assertStringStartsWith('ENC-', $encounter->encounter_number);
    }

    public function test_encounter_casts_status_as_enum(): void
    {
        $encounter = Encounter::factory()->create(['status' => EncounterStatus::PLANNED]);
        $this->assertTrue($encounter->status instanceof EncounterStatus);
        $this->assertSame(EncounterStatus::PLANNED, $encounter->status);
    }

    // ─── EncounterParticipant model ──────────────────────────────────────────

    public function test_participant_is_active_check(): void
    {
        $active = EncounterParticipant::factory()->create(['status' => ParticipantStatus::ACTIVE]);
        $this->assertTrue($active->isActive());
        $completed = EncounterParticipant::factory()->create(['status' => ParticipantStatus::COMPLETED]);
        $this->assertFalse($completed->isActive());
    }

    public function test_participant_complete_sets_left_at(): void
    {
        $participant = EncounterParticipant::factory()->create(['status' => ParticipantStatus::ACTIVE]);
        $participant->complete();
        $this->assertSame(ParticipantStatus::COMPLETED, $participant->status);
        $this->assertNotNull($participant->left_at);
    }

    public function test_shift_duration_is_null_without_joined_at(): void
    {
        $participant = EncounterParticipant::factory()->create([
            'joined_at' => null,
            'left_at' => null,
        ]);
        $this->assertNull($participant->getShiftDurationAttribute());
    }

    // ─── Task model ─────────────────────────────────────────────────────────

    public function test_task_casts_status_as_enum(): void
    {
        $task = Task::factory()->create(['status' => TaskStatus::PENDING]);
        $this->assertTrue($task->status instanceof TaskStatus);
        $this->assertSame(TaskStatus::PENDING, $task->status);
    }

    // ─── Allergy model ──────────────────────────────────────────────────────

    public function test_allergy_defaults_is_active_true(): void
    {
        $allergy = Allergy::factory()->create();
        $this->assertTrue($allergy->is_active);
    }

    public function test_allergy_soft_deletes(): void
    {
        $allergy = Allergy::factory()->create();
        $id = $allergy->id;
        $allergy->delete();
        $this->assertNull(Allergy::find($id));
        $this->assertNotNull(Allergy::withTrashed()->find($id));
    }

    // ─── ClinicalNote model ─────────────────────────────────────────────────

    public function test_note_casts_status_as_enum(): void
    {
        $note = ClinicalNote::factory()->create(['status' => NoteStatus::DRAFT]);
        $this->assertTrue($note->status instanceof NoteStatus);
        $this->assertSame(NoteStatus::DRAFT, $note->status);
    }

    // ─── Enum edge cases ────────────────────────────────────────────────────

    public function test_encounter_priority_values(): void
    {
        $values = EncounterPriority::values();
        $this->assertContains('routine', $values);
        $this->assertContains('urgent', $values);
        $this->assertContains('emergency', $values);
        $this->assertContains('low', $values);
    }

    public function test_participant_role_values(): void
    {
        $values = ParticipantRole::values();
        $this->assertContains('attending', $values);
        $this->assertContains('primary_provider', $values);
        $this->assertContains('consultant', $values);
    }

    public function test_participant_status_values(): void
    {
        $values = ParticipantStatus::values();
        $this->assertContains('active', $values);
        $this->assertContains('completed', $values);
    }

    public function test_discharge_disposition_values(): void
    {
        $values = DischargeDisposition::values();
        $this->assertContains('completed', $values);
        $this->assertContains('transferred', $values);
        $this->assertContains('deceased', $values);
    }

    public function test_allergen_type_values(): void
    {
        $values = AllergenType::values();
        $this->assertContains('medication', $values);
        $this->assertContains('food', $values);
        $this->assertContains('environmental', $values);
    }

    public function test_allergy_severity_values(): void
    {
        $values = AllergySeverity::values();
        $this->assertContains('mild', $values);
        $this->assertContains('moderate', $values);
        $this->assertContains('severe', $values);
    }

    public function test_onset_type_values(): void
    {
        $values = OnsetType::values();
        $this->assertContains('acute', $values);
        $this->assertContains('chronic', $values);
    }

    public function test_request_priority_values(): void
    {
        $values = RequestPriority::values();
        $this->assertContains('routine', $values);
        $this->assertContains('urgent', $values);
        $this->assertContains('low', $values);
        $this->assertContains('emergency', $values);
    }

    public function test_task_outcome_values(): void
    {
        $values = TaskOutcome::values();
        $this->assertContains('completed', $values);
        $this->assertContains('partial', $values);
        $this->assertContains('no_show', $values);
        $this->assertContains('cancelled', $values);
    }
}
