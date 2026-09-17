<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Classes\Services\BedAssignmentService;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Enums\AdmissionRequestStatus;
use Modules\Clinical\Enums\AdtEventType;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\ParticipantRole;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Enums\BedStatus;
use Modules\Core\Enums\WardGenderPolicy;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

/**
 * Covers the inpatient lifecycle fixes: discharge reachability, bed
 * validation and status wiring, cancel audit, pass/leave, admission request
 * withdrawal/expiry, and participant assignment.
 */
class InpatientLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected AdtService $adt;

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
        $this->migrateModules(['Core', 'Patient', 'Clinical']);

        config(['clinical.beds.cleaning_on_discharge' => true, 'clinical.admissions.request_expiry_hours' => 24, 'clinical.discharge.enforce_readiness' => false]);

        $this->adt = app(AdtService::class);
        $this->branch = Branch::factory()->default()->create();
        $this->doctor = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->nurse = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->actingAs($this->doctor);

        $this->patient = Patient::withoutEvents(fn () => Patient::factory()->male()->create(['branch_id' => $this->branch->id]));

        $this->ward = Location::factory()->room()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->bedA = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $this->ward->id, 'is_active' => true]);
        $this->bedB = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $this->ward->id, 'is_active' => true]);
    }

    public function test_admitted_inpatient_can_be_discharged_without_manual_status_changes(): void
    {
        $encounter = $this->adt->admit($this->patient, $this->bedA->id);

        $this->assertSame(EncounterStatus::IN_PROGRESS, $encounter->status);

        $discharged = $this->adt->discharge($encounter, actedBy: $this->doctor->id);

        $this->assertSame(EncounterStatus::FINISHED, $discharged->status);
        $this->assertNull($discharged->bed_id);
        $this->assertSame(BedStatus::CLEANING, $this->bedA->fresh()->bedStatus());
        $this->assertNull($this->bedA->fresh()->status_reference);
    }

    public function test_discharge_leaves_bed_available_when_cleaning_is_disabled(): void
    {
        config(['clinical.beds.cleaning_on_discharge' => false]);

        $encounter = $this->adt->admit($this->patient, $this->bedA->id);
        $this->adt->discharge($encounter);

        $this->assertSame(BedStatus::AVAILABLE, $this->bedA->fresh()->bedStatus());
    }

    public function test_admit_rejects_inactive_non_bed_foreign_and_blocked_beds(): void
    {
        $inactive = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $this->ward->id, 'is_active' => false]);
        $blocked = Location::factory()->bed()->withStatus(BedStatus::BLOCKED)->create(['branch_id' => $this->branch->id, 'parent_id' => $this->ward->id]);
        $otherBranchBed = Location::factory()->bed()->create(['branch_id' => Branch::factory()->create()->id, 'parent_id' => $this->ward->id]);

        foreach ([$inactive->id, $this->ward->id, $blocked->id, $otherBranchBed->id, (string) \Illuminate\Support\Str::uuid()] as $bedId) {
            try {
                $this->adt->admit($this->patient, $bedId);
                $this->fail("Bed {$bedId} should have been rejected");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $this->assertFalse($this->patient->activeEncounter()->whereNotNull('bed_id')->exists());
    }

    public function test_reserved_bed_is_only_assignable_to_its_admission(): void
    {
        $waiting = Encounter::factory()->forPatient($this->patient)->outpatient()->create([
            'branch_id' => $this->branch->id,
            'status' => EncounterStatus::ARRIVED,
        ]);
        $request = $this->adt->requestAdmission($waiting, $this->ward->id, bedId: $this->bedA->id, requestedBy: $this->doctor->id);

        $this->assertSame(BedStatus::RESERVED, $this->bedA->fresh()->bedStatus());

        $other = Patient::withoutEvents(fn () => Patient::factory()->male()->create(['branch_id' => $this->branch->id]));

        try {
            $this->adt->admit($other, $this->bedA->id);
            $this->fail('A bed reserved for another admission was assigned');
        } catch (\InvalidArgumentException) {
            $this->assertSame(BedStatus::RESERVED, $this->bedA->fresh()->bedStatus());
        }

        $admitted = $this->adt->acceptAdmission($request, $this->bedA->id, actedBy: $this->nurse->id);

        $this->assertSame(BedStatus::OCCUPIED, $this->bedA->fresh()->bedStatus());
        $this->assertSame($admitted->id, $this->bedA->fresh()->status_reference);
    }

    public function test_accepting_with_a_different_bed_releases_the_reserved_one(): void
    {
        $waiting = Encounter::factory()->forPatient($this->patient)->outpatient()->create(['branch_id' => $this->branch->id, 'status' => EncounterStatus::ARRIVED]);
        $request = $this->adt->requestAdmission($waiting, $this->ward->id, bedId: $this->bedA->id, requestedBy: $this->doctor->id);

        $this->adt->acceptAdmission($request, $this->bedB->id, actedBy: $this->nurse->id);

        $this->assertSame(BedStatus::AVAILABLE, $this->bedA->fresh()->bedStatus());
        $this->assertSame(BedStatus::OCCUPIED, $this->bedB->fresh()->bedStatus());
    }

    public function test_ward_gender_policy_is_enforced(): void
    {
        $this->ward->update(['gender_policy' => WardGenderPolicy::FEMALE]);

        $this->expectException(\InvalidArgumentException::class);
        $this->adt->admit($this->patient, $this->bedA->id);
    }

    public function test_emergency_patients_must_be_triaged_before_acceptance(): void
    {
        $emergency = Encounter::factory()->forPatient($this->patient)->create([
            'branch_id' => $this->branch->id,
            'type' => EncounterType::EMERGENCY,
            'status' => EncounterStatus::ARRIVED,
        ]);
        $request = $this->adt->requestAdmission($emergency, $this->ward->id, requestedBy: $this->doctor->id);

        try {
            $this->adt->acceptAdmission($request, $this->bedA->id, actedBy: $this->nurse->id);
            $this->fail('Untriaged emergency patient was admitted');
        } catch (\InvalidArgumentException) {
            $this->assertSame(EncounterStatus::ARRIVED, $emergency->fresh()->status);
        }

        app(EncounterService::class)->triage($emergency->fresh(), $emergency->priority);

        $admitted = $this->adt->acceptAdmission($request->fresh(), $this->bedA->id, actedBy: $this->nurse->id);

        $this->assertSame(EncounterStatus::IN_PROGRESS, $admitted->status);
        $this->assertNull($admitted->metadata['auto_triaged'] ?? null);
    }

    public function test_cancel_logs_an_adt_event_and_frees_the_bed(): void
    {
        $encounter = $this->adt->admit($this->patient, $this->bedA->id);

        $cancelled = $this->adt->cancel($encounter, 'Admitted in error', $this->doctor->id);

        $this->assertSame(EncounterStatus::CANCELLED, $cancelled->status);
        $this->assertNull($cancelled->bed_id);
        $this->assertNull($cancelled->location_id);
        $this->assertSame(BedStatus::CLEANING, $this->bedA->fresh()->bedStatus());
        $this->assertDatabaseHas('encounter_location_events', [
            'encounter_id' => $encounter->id,
            'event_type' => AdtEventType::Cancelled->value,
            'from_bed_id' => $this->bedA->id,
            'notes' => 'Admitted in error',
        ]);
    }

    public function test_pass_keeps_the_bed_and_return_resumes_care(): void
    {
        $encounter = $this->adt->admit($this->patient, $this->bedA->id);

        $onPass = $this->adt->sendOnPass($encounter, 'Home for the weekend', now()->addDays(2), $this->doctor->id);

        $this->assertSame(EncounterStatus::ON_LEAVE, $onPass->status);
        $this->assertSame($this->bedA->id, $onPass->bed_id);
        $this->assertSame(BedStatus::OCCUPIED, $this->bedA->fresh()->bedStatus());
        $this->assertFalse(app(BedAssignmentService::class)->getAvailableBeds($this->ward->id)->has($this->bedA->id));
        $this->assertSame('Home for the weekend', $onPass->metadata['pass']['reason']);
        $this->assertDatabaseHas('encounter_location_events', ['encounter_id' => $encounter->id, 'event_type' => AdtEventType::OnPass->value]);

        $back = $this->adt->returnFromPass($onPass, $this->nurse->id);

        $this->assertSame(EncounterStatus::IN_PROGRESS, $back->status);
        $this->assertNotNull($back->metadata['pass']['returned_at']);
        $this->assertDatabaseHas('encounter_location_events', ['encounter_id' => $encounter->id, 'event_type' => AdtEventType::ReturnedFromPass->value]);
    }

    public function test_pass_requires_an_admitted_inpatient(): void
    {
        $outpatient = Encounter::factory()->forPatient($this->patient)->outpatient()->create(['branch_id' => $this->branch->id, 'status' => EncounterStatus::IN_PROGRESS]);

        $this->expectException(\InvalidArgumentException::class);
        $this->adt->sendOnPass($outpatient);
    }

    public function test_requester_can_withdraw_a_pending_request_and_the_reservation_is_released(): void
    {
        $waiting = Encounter::factory()->forPatient($this->patient)->outpatient()->create(['branch_id' => $this->branch->id, 'status' => EncounterStatus::ARRIVED]);
        $request = $this->adt->requestAdmission($waiting, $this->ward->id, bedId: $this->bedA->id, requestedBy: $this->doctor->id);

        $this->assertTrue($request->isCancellableBy($this->doctor->id));
        $this->assertFalse($request->isCancellableBy($this->nurse->id));
        $this->assertTrue($request->isCancellableBy($this->nurse->id, canDecide: true));

        $withdrawn = $this->adt->cancelAdmissionRequest($request, 'Patient improved', $this->doctor->id);

        $this->assertSame(AdmissionRequestStatus::Cancelled, $withdrawn->status);
        $this->assertSame(BedStatus::AVAILABLE, $this->bedA->fresh()->bedStatus());
        $this->assertFalse($waiting->fresh()->hasPendingAdmissionRequest());
        $this->assertDatabaseHas('encounter_location_events', ['encounter_id' => $waiting->id, 'event_type' => AdtEventType::AdmissionCancelled->value]);

        $this->expectException(\InvalidArgumentException::class);
        $this->adt->cancelAdmissionRequest($withdrawn);
    }

    public function test_expiry_command_expires_only_overdue_requests(): void
    {
        $waiting = Encounter::factory()->forPatient($this->patient)->outpatient()->create(['branch_id' => $this->branch->id, 'status' => EncounterStatus::ARRIVED]);
        $stale = $this->adt->requestAdmission($waiting, $this->ward->id, bedId: $this->bedA->id, requestedBy: $this->doctor->id);
        $stale->forceFill(['expires_at' => now()->subMinute()])->save();

        $otherPatient = Patient::withoutEvents(fn () => Patient::factory()->create(['branch_id' => $this->branch->id]));
        $fresh = Encounter::factory()->forPatient($otherPatient)->outpatient()->create(['branch_id' => $this->branch->id, 'status' => EncounterStatus::ARRIVED]);
        $recent = $this->adt->requestAdmission($fresh, $this->ward->id, requestedBy: $this->doctor->id);

        $this->artisan('clinical:expire-admission-requests')->assertSuccessful();

        $this->assertSame(AdmissionRequestStatus::Expired, $stale->fresh()->status);
        $this->assertSame(AdmissionRequestStatus::Pending, $recent->fresh()->status);
        $this->assertSame(BedStatus::AVAILABLE, $this->bedA->fresh()->bedStatus());
        $this->assertDatabaseHas('encounter_location_events', ['encounter_id' => $waiting->id, 'event_type' => AdtEventType::AdmissionExpired->value]);
    }

    public function test_expiry_can_be_disabled(): void
    {
        config(['clinical.admissions.request_expiry_hours' => null]);

        $waiting = Encounter::factory()->forPatient($this->patient)->outpatient()->create(['branch_id' => $this->branch->id, 'status' => EncounterStatus::ARRIVED]);
        $request = $this->adt->requestAdmission($waiting, $this->ward->id, requestedBy: $this->doctor->id);

        $this->assertNull($request->expires_at);
    }

    public function test_closing_the_encounter_withdraws_pending_requests(): void
    {
        $waiting = Encounter::factory()->forPatient($this->patient)->outpatient()->create(['branch_id' => $this->branch->id, 'status' => EncounterStatus::IN_PROGRESS]);
        $request = $this->adt->requestAdmission($waiting, $this->ward->id, bedId: $this->bedA->id, requestedBy: $this->doctor->id);

        $this->adt->cancel($waiting, 'Left before admission');

        $this->assertSame(AdmissionRequestStatus::Cancelled, $request->fresh()->status);
        $this->assertSame(BedStatus::AVAILABLE, $this->bedA->fresh()->bedStatus());
    }

    public function test_nurse_assignment_is_a_handover(): void
    {
        $encounter = $this->adt->admit($this->patient, $this->bedA->id);
        $service = app(EncounterService::class);

        $first = $service->assignNurse($encounter, $this->nurse->id, $this->doctor->id);
        $second = $service->assignNurse($encounter->fresh(), $this->doctor->id, $this->doctor->id);

        $this->assertSame(ParticipantRole::NURSE, $first->fresh()->role);
        $this->assertSame('completed', $first->fresh()->status->value);
        $this->assertSame('active', $second->fresh()->status->value);
        $this->assertSame(1, $encounter->fresh()->participants()->where('role', ParticipantRole::NURSE)->where('status', 'active')->count());
    }

    public function test_internal_transfer_moves_bed_status_with_the_patient(): void
    {
        $encounter = $this->adt->admit($this->patient, $this->bedA->id);

        $moved = $this->adt->transferInternal($encounter, $this->bedB->id);

        $this->assertSame($this->bedB->id, $moved->bed_id);
        $this->assertSame(BedStatus::CLEANING, $this->bedA->fresh()->bedStatus());
        $this->assertSame(BedStatus::OCCUPIED, $this->bedB->fresh()->bedStatus());
        $this->assertSame($moved->id, $this->bedB->fresh()->status_reference);
    }
}
