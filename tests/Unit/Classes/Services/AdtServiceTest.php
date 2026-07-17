<?php

namespace Modules\Clinical\Tests\Unit\Classes\Services;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Enums\AdtDestinationType;
use Modules\Clinical\Enums\AdtEventType;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterLocationEvent;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class AdtServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected AdtService $service;

    protected Branch $branch;

    protected User $user;

    protected Patient $patient;

    protected Location $ward;

    protected Location $bedA;

    protected Location $bedB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Clinical']);

        $this->service = app(AdtService::class);
        $this->branch = Branch::factory()->create();
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->actingAs($this->user);

        $this->patient = Patient::withoutEvents(
            fn () => Patient::factory()->create(['branch_id' => $this->branch->id])
        );

        $this->ward = Location::factory()->room()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Ward A',
            'is_active' => true,
        ]);

        $this->bedA = Location::factory()->bed()->create([
            'branch_id' => $this->branch->id,
            'parent_id' => $this->ward->id,
            'name' => 'Bed A1',
            'is_active' => true,
        ]);

        $this->bedB = Location::factory()->bed()->create([
            'branch_id' => $this->branch->id,
            'parent_id' => $this->ward->id,
            'name' => 'Bed A2',
            'is_active' => true,
        ]);
    }

    public function test_admit_creates_inpatient_encounter_assigns_bed_and_logs_event(): void
    {
        $encounter = $this->service->admit(
            $this->patient,
            $this->bedA->id,
            notes: 'Admission notes',
        );

        $this->assertSame(EncounterType::INPATIENT, $encounter->type);
        $this->assertSame(EncounterStatus::ARRIVED, $encounter->status);
        $this->assertSame($this->bedA->id, $encounter->bed_id);
        $this->assertSame($this->ward->id, $encounter->location_id);
        $this->assertSame($this->branch->id, $encounter->branch_id);

        $this->assertDatabaseHas('encounter_location_events', [
            'encounter_id' => $encounter->id,
            'patient_id' => $this->patient->id,
            'branch_id' => $this->branch->id,
            'event_type' => AdtEventType::Admitted->value,
            'to_bed_id' => $this->bedA->id,
        ]);
    }

    public function test_transfer_internal_moves_bed_and_logs_event(): void
    {
        $encounter = $this->service->admit($this->patient, $this->bedA->id);
        $encounter->update(['status' => EncounterStatus::IN_PROGRESS]);

        $moved = $this->service->transferInternal($encounter->fresh(), $this->bedB->id, notes: 'Moved');

        $this->assertSame($this->bedB->id, $moved->bed_id);
        $this->assertSame(EncounterStatus::IN_PROGRESS, $moved->status);

        $event = EncounterLocationEvent::query()
            ->where('encounter_id', $moved->id)
            ->where('event_type', AdtEventType::TransferredInternal)
            ->latest('occurred_at')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($this->bedA->id, $event->from_bed_id);
        $this->assertSame($this->bedB->id, $event->to_bed_id);
        $this->assertSame(AdtDestinationType::InternalUnit, $event->destination_type);
    }

    public function test_transfer_out_finishes_encounter_and_clears_bed(): void
    {
        $encounter = $this->service->admit($this->patient, $this->bedA->id);
        $encounter->update(['status' => EncounterStatus::IN_PROGRESS]);

        $finished = $this->service->transferOut(
            $encounter->fresh(),
            AdtDestinationType::ExternalFacility,
            destinationLabel: 'City Referral Hospital',
        );

        $this->assertSame(EncounterStatus::FINISHED, $finished->status);
        $this->assertSame(DischargeDisposition::TRANSFERRED, $finished->discharge_disposition);
        $this->assertSame('City Referral Hospital', $finished->transfer_destination);
        $this->assertNull($finished->bed_id);

        $this->assertDatabaseHas('encounter_location_events', [
            'encounter_id' => $finished->id,
            'event_type' => AdtEventType::TransferredOut->value,
            'destination_label' => 'City Referral Hospital',
        ]);
    }

    public function test_transfer_in_creates_inpatient_encounter_with_metadata(): void
    {
        $encounter = $this->service->transferIn(
            $this->patient,
            $this->bedA->id,
            sourceLabel: 'District Hospital',
            notes: 'Received',
        );

        $this->assertSame(EncounterType::INPATIENT, $encounter->type);
        $this->assertSame($this->bedA->id, $encounter->bed_id);
        $this->assertSame('District Hospital', data_get($encounter->metadata, 'transfer_in.source_label'));

        $this->assertDatabaseHas('encounter_location_events', [
            'encounter_id' => $encounter->id,
            'event_type' => AdtEventType::TransferredIn->value,
            'destination_label' => 'District Hospital',
        ]);
    }

    public function test_discharge_logs_discharged_event(): void
    {
        $encounter = $this->service->admit($this->patient, $this->bedA->id);
        $encounter->update(['status' => EncounterStatus::IN_PROGRESS]);

        $finished = $this->service->discharge(
            $encounter->fresh(),
            DischargeDisposition::COMPLETED,
            notes: 'Home',
        );

        $this->assertSame(EncounterStatus::FINISHED, $finished->status);
        $this->assertNull($finished->bed_id);
        $this->assertSame('Home', data_get($finished->metadata, 'discharge_notes'));

        $this->assertDatabaseHas('encounter_location_events', [
            'encounter_id' => $finished->id,
            'event_type' => AdtEventType::Discharged->value,
        ]);
    }

    public function test_admit_preserves_encounter_branch_on_location_event_when_actor_branch_differs(): void
    {
        $otherBranch = Branch::factory()->create();
        $this->user->update(['branch_id' => $otherBranch->id]);
        $this->actingAs($this->user->fresh());

        $encounter = $this->service->admit($this->patient, $this->bedA->id);

        $this->assertSame($this->branch->id, $encounter->branch_id);
        $this->assertDatabaseHas('encounter_location_events', [
            'encounter_id' => $encounter->id,
            'branch_id' => $this->branch->id,
            'event_type' => AdtEventType::Admitted->value,
        ]);
    }

    public function test_admit_rejects_occupied_bed(): void
    {
        $otherPatient = Patient::withoutEvents(
            fn () => Patient::factory()->create(['branch_id' => $this->branch->id])
        );

        $this->service->admit($otherPatient, $this->bedA->id);

        $this->expectException(\RuntimeException::class);
        $this->service->admit($this->patient, $this->bedA->id);
    }

    public function test_transfer_internal_requires_active_or_planned_encounter(): void
    {
        $encounter = Encounter::factory()
            ->forPatient($this->patient)
            ->inpatient()
            ->finished()
            ->create(['branch_id' => $this->branch->id, 'bed_id' => $this->bedA->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->transferInternal($encounter, $this->bedB->id);
    }

    public function test_transfer_in_rejects_when_open_encounter_exists(): void
    {
        Encounter::factory()
            ->forPatient($this->patient)
            ->outpatient()
            ->create([
                'branch_id' => $this->branch->id,
                'status' => EncounterStatus::IN_PROGRESS,
            ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->transferIn($this->patient, $this->bedA->id, sourceLabel: 'Elsewhere');
    }

    public function test_admit_rejects_open_non_inpatient_encounter(): void
    {
        Encounter::factory()
            ->forPatient($this->patient)
            ->outpatient()
            ->create([
                'branch_id' => $this->branch->id,
                'status' => EncounterStatus::ARRIVED,
            ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->admit($this->patient, $this->bedA->id);
    }
}
