<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clinical\Classes\Services\DailyWardStatementService;
use Modules\Clinical\Classes\Services\WardInchargeNoteService;
use Modules\Clinical\Enums\AdtEventType;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\WardNoteStatus;
use Modules\Clinical\Enums\WardNoteType;
use Modules\Clinical\Enums\WardShift;
use Modules\Clinical\Enums\WardStatementPeriod;
use Modules\Clinical\Enums\WardStatementStatus;
use Modules\Clinical\Models\DischargeSummary;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterLocationEvent;
use Modules\Clinical\Support\WardStaffResolver;
use Modules\Core\Classes\Services\WardAccessService;
use Modules\Core\Enums\BedStatus;
use Modules\Core\Enums\LocationType;
use Modules\Core\Enums\WardStaffRole;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Core\Models\LocationStaff;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class WardInchargeAndDailyStatementTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected Location $ward;

    protected Location $bed1;

    protected Location $bed2;

    protected User $incharge;

    protected User $deputy;

    protected User $wardNurse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->incharge = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->deputy = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->wardNurse = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);

        $this->ward = Location::factory()->create([
            'branch_id' => $this->branch->id,
            'type' => LocationType::ROOM,
            'name' => 'Male Medical Ward',
            'is_active' => true,
            'nurse_in_charge_id' => $this->incharge->id,
        ]);

        $this->bed1 = Location::factory()->create([
            'branch_id' => $this->branch->id,
            'parent_id' => $this->ward->id,
            'type' => LocationType::BED,
            'name' => 'Bed 1',
            'is_active' => true,
            'status' => BedStatus::AVAILABLE,
        ]);

        $this->bed2 = Location::factory()->create([
            'branch_id' => $this->branch->id,
            'parent_id' => $this->ward->id,
            'type' => LocationType::BED,
            'name' => 'Bed 2',
            'is_active' => true,
            'status' => BedStatus::BLOCKED,
        ]);

        LocationStaff::create([
            'location_id' => $this->ward->id,
            'user_id' => $this->deputy->id,
            'role' => WardStaffRole::DEPUTY_INCHARGE,
            'is_active' => true,
            'assigned_by' => $this->incharge->id,
            'assigned_at' => now(),
        ]);

        LocationStaff::create([
            'location_id' => $this->ward->id,
            'user_id' => $this->wardNurse->id,
            'role' => WardStaffRole::WARD_NURSE,
            'is_active' => true,
            'assigned_by' => $this->incharge->id,
            'assigned_at' => now(),
        ]);
    }

    public function test_ward_access_recognizes_primary_and_deputy_incharges_and_resolves_default_ward(): void
    {
        $service = app(WardAccessService::class);

        $this->assertTrue($service->isWardIncharge($this->incharge, $this->ward));
        $this->assertTrue($service->isWardIncharge($this->deputy, $this->ward));
        $this->assertFalse($service->isWardIncharge($this->wardNurse, $this->ward));

        $this->assertSame($this->ward->id, $service->defaultWardIdFor($this->incharge, $this->branch->id));
        $this->assertSame($this->ward->id, $service->defaultWardIdFor($this->deputy, $this->branch->id));
        $this->assertSame($this->ward->id, $service->defaultWardIdFor($this->wardNurse, $this->branch->id));

        $resolvedStaff = WardStaffResolver::forWard($this->ward)->pluck('id')->all();
        $this->assertEqualsCanonicalizing(
            [$this->incharge->id, $this->deputy->id, $this->wardNurse->id],
            $resolvedStaff,
        );
    }

    public function test_incharge_note_handover_acknowledgement_and_resolution_lifecycle(): void
    {
        $service = app(WardInchargeNoteService::class);

        $note = $service->create($this->ward, [
            'note_type' => WardNoteType::SHIFT_HANDOVER,
            'shift' => WardShift::MORNING,
            'title' => 'Morning to Afternoon Handover',
            'body' => 'All patients stable. Oxygen cylinder on Bed 1 at 50%.',
            'structured_payload' => [
                'emergency_trolley_checked' => true,
                'controlled_drugs_tallied' => true,
                'equipment_oxygen_checked' => true,
            ],
            'is_pinned' => true,
        ], $this->incharge);

        $this->assertTrue($note->requires_acknowledgement);
        $this->assertTrue($note->is_pinned);
        $this->assertSame(WardNoteStatus::OPEN, $note->status);

        $acknowledged = $service->acknowledge($note, $this->deputy);
        $this->assertSame($this->deputy->id, $acknowledged->acknowledged_by);
        $this->assertNotNull($acknowledged->acknowledged_at);
        $this->assertSame(WardNoteStatus::ACKNOWLEDGED, $acknowledged->status);

        $resolved = $service->resolve($acknowledged, 'Oxygen cylinder refilled.', $this->deputy);
        $this->assertSame(WardNoteStatus::RESOLVED, $resolved->status);
        $this->assertSame('Oxygen cylinder refilled.', $resolved->resolution_notes);
    }

    public function test_daily_ward_statement_computes_reconciliation_and_freezes_on_submission(): void
    {
        $patient1 = Patient::factory()->create(['branch_id' => $this->branch->id]);
        $patient2 = Patient::factory()->create(['branch_id' => $this->branch->id]);

        $activeEncounter = Encounter::factory()->create([
            'branch_id' => $this->branch->id,
            'patient_id' => $patient1->id,
            'location_id' => $this->ward->id,
            'bed_id' => $this->bed1->id,
            'status' => EncounterStatus::IN_PROGRESS,
        ]);

        $deceasedEncounter = Encounter::factory()->create([
            'branch_id' => $this->branch->id,
            'patient_id' => $patient2->id,
            'location_id' => $this->ward->id,
            'status' => EncounterStatus::FINISHED,
        ]);

        EncounterLocationEvent::factory()->create([
            'branch_id' => $this->branch->id,
            'encounter_id' => $activeEncounter->id,
            'patient_id' => $patient1->id,
            'event_type' => AdtEventType::Admitted,
            'to_location_id' => $this->ward->id,
            'to_bed_id' => $this->bed1->id,
            'occurred_at' => now(),
        ]);

        EncounterLocationEvent::factory()->create([
            'branch_id' => $this->branch->id,
            'encounter_id' => $deceasedEncounter->id,
            'patient_id' => $patient2->id,
            'event_type' => AdtEventType::Discharged,
            'from_location_id' => $this->ward->id,
            'occurred_at' => now(),
        ]);

        DischargeSummary::factory()->create([
            'encounter_id' => $deceasedEncounter->id,
            'patient_id' => $patient2->id,
            'discharge_disposition' => DischargeDisposition::DECEASED,
        ]);

        $service = app(DailyWardStatementService::class);
        $draft = $service->generateOrRefreshDraft($this->ward, now(), WardStatementPeriod::DAILY, $this->incharge);

        $this->assertSame(WardStatementStatus::DRAFT, $draft->status);
        $this->assertSame(2, $draft->total_beds);
        $this->assertSame(1, $draft->blocked_beds);
        $this->assertSame(1, $draft->admissions_count);
        $this->assertSame(1, $draft->deaths_count);
        $this->assertSame(1, $draft->remaining_total);

        $submitted = $service->submit($draft, [
            'general_remarks' => 'Ward running smoothly.',
        ], $this->incharge);

        $this->assertSame(WardStatementStatus::SUBMITTED, $submitted->status);
        $this->assertSame($this->incharge->id, $submitted->submitted_by);
        $this->assertNotNull($submitted->submitted_at);

        $this->expectException(\RuntimeException::class);
        $service->submit($submitted, [], $this->incharge);
    }
}
