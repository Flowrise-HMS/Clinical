<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Classes\Actions\EncounterActions;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Classes\Services\BedAssignmentService;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Enums\AdmissionRequestStatus;
use Modules\Clinical\Enums\AdtEventType;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\ParticipantRole;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Enums\BedStatus;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

/**
 * A doctor requests admission to a ward; ward staff accept (confirming the
 * bed) or reject it. Nothing occupies a bed until the request is accepted.
 */
class AdmissionRequestFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected User $doctor;

    protected User $nurse;

    protected Patient $patient;

    protected Location $ward;

    protected Location $bedA;

    protected Location $bedB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff']);

        $this->branch = Branch::factory()->default()->create();
        $this->doctor = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->nurse = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->patient = Patient::withoutEvents(
            fn () => Patient::factory()->create(['branch_id' => $this->branch->id])
        );

        $this->ward = Location::factory()->room()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->bedA = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $this->ward->id, 'is_active' => true]);
        $this->bedB = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $this->ward->id, 'is_active' => true]);
    }

    public function test_request_does_not_occupy_a_bed_and_logs_an_event(): void
    {
        $encounter = $this->outpatientEncounter(EncounterStatus::ARRIVED);

        $request = app(AdtService::class)->requestAdmission(
            $encounter,
            $this->ward->id,
            bedId: $this->bedA->id,
            notes: 'Needs observation overnight',
            requestedBy: $this->doctor->id,
        );

        $encounter->refresh();

        $this->assertSame(AdmissionRequestStatus::Pending, $request->status);
        $this->assertSame($this->doctor->id, $request->requested_by);
        $this->assertNull($encounter->bed_id);
        $this->assertSame(EncounterStatus::ARRIVED, $encounter->status);
        $this->assertTrue($encounter->hasPendingAdmissionRequest());
        // The preferred bed is held for this request, so it leaves the general pool but stays pickable for the acceptor.
        $this->assertSame(BedStatus::RESERVED, $this->bedA->fresh()->bedStatus());
        $this->assertSame($request->id, $this->bedA->fresh()->status_reference);
        $this->assertFalse(app(BedAssignmentService::class)->getAvailableBeds($this->ward->id)->has($this->bedA->id));
        $this->assertTrue(app(BedAssignmentService::class)->getAvailableBeds($this->ward->id, $encounter->id, $request->id)->has($this->bedA->id));
        $this->assertNotNull($request->expires_at);
        $this->assertDatabaseHas('encounter_location_events', [
            'encounter_id' => $encounter->id,
            'event_type' => AdtEventType::AdmissionRequested->value,
            'acted_by' => $this->doctor->id,
        ]);
    }

    public function test_only_one_pending_request_per_encounter(): void
    {
        $encounter = $this->outpatientEncounter(EncounterStatus::ARRIVED);
        app(AdtService::class)->requestAdmission($encounter, $this->ward->id, requestedBy: $this->doctor->id);

        $this->expectException(\InvalidArgumentException::class);

        app(AdtService::class)->requestAdmission($encounter, $this->ward->id, requestedBy: $this->doctor->id);
    }

    public function test_accept_admits_patient_to_confirmed_bed_and_makes_encounter_inpatient(): void
    {
        $encounter = $this->outpatientEncounter(EncounterStatus::ARRIVED);
        $request = app(AdtService::class)->requestAdmission(
            $encounter,
            $this->ward->id,
            bedId: $this->bedA->id,
            requestedBy: $this->doctor->id,
        );

        // Ward chooses a different bed from the one the doctor preferred.
        $admitted = app(AdtService::class)->acceptAdmission($request, $this->bedB->id, notes: 'Bed A being cleaned', actedBy: $this->nurse->id);

        $request->refresh();

        $this->assertSame(AdmissionRequestStatus::Accepted, $request->status);
        $this->assertSame($this->bedB->id, $request->assigned_bed_id);
        $this->assertSame($this->nurse->id, $request->decided_by);
        $this->assertNotNull($request->decided_at);
        $this->assertSame($this->bedB->id, $admitted->bed_id);
        $this->assertSame($this->ward->id, $admitted->location_id);
        $this->assertSame(EncounterType::INPATIENT, $admitted->type);
        $this->assertNotNull($admitted->admitted_at);
        $this->assertFalse($admitted->hasPendingAdmissionRequest());
        $this->assertDatabaseHas('encounter_location_events', [
            'encounter_id' => $encounter->id,
            'event_type' => AdtEventType::BedAssigned->value,
            'to_bed_id' => $this->bedB->id,
        ]);
    }

    public function test_accept_starts_ward_care_for_a_planned_encounter(): void
    {
        $encounter = $this->outpatientEncounter(EncounterStatus::PLANNED);
        $request = app(AdtService::class)->requestAdmission($encounter, $this->ward->id, requestedBy: $this->doctor->id);

        $admitted = app(AdtService::class)->acceptAdmission($request, $this->bedA->id, actedBy: $this->nurse->id);

        $this->assertSame(EncounterStatus::IN_PROGRESS, $admitted->status);
        $this->assertTrue($admitted->canTransitionTo(EncounterStatus::FINISHED));
        $this->assertSame($this->bedA->id, $admitted->bed_id);
        $this->assertSame(BedStatus::OCCUPIED, $this->bedA->fresh()->bedStatus());
        $this->assertDatabaseHas('encounter_participants', ['encounter_id' => $encounter->id, 'user_id' => $this->doctor->id, 'role' => ParticipantRole::ATTENDING->value]);
        $this->assertDatabaseHas('encounter_participants', ['encounter_id' => $encounter->id, 'user_id' => $this->nurse->id, 'role' => ParticipantRole::NURSE->value]);
        $this->assertDatabaseHas('encounter_location_events', [
            'encounter_id' => $encounter->id,
            'event_type' => AdtEventType::Admitted->value,
        ]);
    }

    public function test_accept_rejects_bed_outside_requested_ward_or_occupied(): void
    {
        $otherWard = Location::factory()->room()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $otherBed = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $otherWard->id, 'is_active' => true]);

        $encounter = $this->outpatientEncounter(EncounterStatus::ARRIVED);
        $request = app(AdtService::class)->requestAdmission($encounter, $this->ward->id, requestedBy: $this->doctor->id);

        try {
            app(AdtService::class)->acceptAdmission($request, $otherBed->id, actedBy: $this->nurse->id);
            $this->fail('Bed from another ward was accepted.');
        } catch (\InvalidArgumentException) {
        }

        Encounter::factory()->forPatient(Patient::withoutEvents(fn () => Patient::factory()->create(['branch_id' => $this->branch->id])))
            ->inpatient()
            ->create(['branch_id' => $this->branch->id, 'status' => EncounterStatus::ARRIVED, 'bed_id' => $this->bedA->id]);

        $this->expectException(\RuntimeException::class);
        app(AdtService::class)->acceptAdmission($request, $this->bedA->id, actedBy: $this->nurse->id);
    }

    public function test_reject_records_reason_and_keeps_encounter_open(): void
    {
        $encounter = $this->outpatientEncounter(EncounterStatus::TRIAGED);
        $request = app(AdtService::class)->requestAdmission($encounter, $this->ward->id, requestedBy: $this->doctor->id);

        $rejected = app(AdtService::class)->rejectAdmission($request, 'Ward full — no isolation bed', actedBy: $this->nurse->id);
        $encounter->refresh();

        $this->assertSame(AdmissionRequestStatus::Rejected, $rejected->status);
        $this->assertSame('Ward full — no isolation bed', $rejected->decision_notes);
        $this->assertSame($this->nurse->id, $rejected->decided_by);
        $this->assertSame(EncounterStatus::TRIAGED, $encounter->status);
        $this->assertSame(EncounterType::OUTPATIENT, $encounter->type);
        $this->assertNull($encounter->bed_id);
        $this->assertFalse($encounter->hasPendingAdmissionRequest());
        $this->assertSame($rejected->id, $encounter->latestAdmissionRequest()->first()?->id);
        $this->assertDatabaseHas('encounter_location_events', [
            'encounter_id' => $encounter->id,
            'event_type' => AdtEventType::AdmissionRejected->value,
            'notes' => 'Ward full — no isolation bed',
        ]);

        // The doctor can ask again for another ward.
        $again = app(AdtService::class)->requestAdmission($encounter, $this->ward->id, requestedBy: $this->doctor->id);
        $this->assertTrue($again->isPending());
    }

    public function test_decided_requests_cannot_be_decided_again(): void
    {
        $encounter = $this->outpatientEncounter(EncounterStatus::ARRIVED);
        $request = app(AdtService::class)->requestAdmission($encounter, $this->ward->id, requestedBy: $this->doctor->id);
        app(AdtService::class)->rejectAdmission($request, 'No', actedBy: $this->nurse->id);

        $this->expectException(\InvalidArgumentException::class);
        app(AdtService::class)->acceptAdmission($request, $this->bedA->id, actedBy: $this->nurse->id);
    }

    public function test_action_visibility_follows_request_state(): void
    {
        $encounter = $this->outpatientEncounter(EncounterStatus::ARRIVED);

        $this->assertTrue(EncounterActions::isAdmitVisible($encounter));
        $this->assertFalse(EncounterActions::isAdmissionDecisionVisible($encounter));
        $this->assertTrue(EncounterActions::isCompleteVisible($encounter));

        $request = app(AdtService::class)->requestAdmission($encounter, $this->ward->id, requestedBy: $this->doctor->id);
        $encounter->refresh();

        $this->assertFalse(EncounterActions::isAdmitVisible($encounter));
        $this->assertTrue(EncounterActions::isAdmissionDecisionVisible($encounter));

        $admitted = app(AdtService::class)->acceptAdmission($request, $this->bedA->id, actedBy: $this->nurse->id);

        $this->assertFalse(EncounterActions::isAdmitVisible($admitted));
        $this->assertFalse(EncounterActions::isAdmissionDecisionVisible($admitted));
        $this->assertFalse(EncounterActions::isCompleteVisible($admitted), 'Admitted inpatients leave via discharge, not complete.');
    }

    public function test_complete_finishes_an_arrived_outpatient_encounter(): void
    {
        $encounter = $this->outpatientEncounter(EncounterStatus::ARRIVED);

        $completed = app(EncounterService::class)->completeEncounter($encounter, notes: 'Seen and treated', completedBy: $this->doctor->id);

        $this->assertSame(EncounterStatus::FINISHED, $completed->status);
        $this->assertSame(DischargeDisposition::COMPLETED, $completed->discharge_disposition);
        $this->assertSame($this->doctor->id, $completed->discharged_by);
        $this->assertNotNull($completed->discharged_at);
        $this->assertSame('Seen and treated', $completed->metadata['completion_notes'] ?? null);
        $this->assertNull($this->patient->activeEncounter()->first());
    }

    public function test_complete_refuses_planned_and_finished_encounters(): void
    {
        $planned = $this->outpatientEncounter(EncounterStatus::PLANNED);

        try {
            app(EncounterService::class)->completeEncounter($planned);
            $this->fail('Planned encounter was completed.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(EncounterStatus::PLANNED, $planned->refresh()->status);

        $finished = $this->outpatientEncounter(EncounterStatus::FINISHED);

        $this->expectException(\InvalidArgumentException::class);
        app(EncounterService::class)->completeEncounter($finished);
    }

    protected function outpatientEncounter(EncounterStatus $status): Encounter
    {
        return Encounter::factory()
            ->forPatient($this->patient)
            ->outpatient()
            ->create([
                'branch_id' => $this->branch->id,
                'status' => $status,
                'bed_id' => null,
                'created_by' => $this->doctor->id,
            ]);
    }
}
