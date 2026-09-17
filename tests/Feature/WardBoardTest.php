<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Classes\Services\WardBoardService;
use Modules\Clinical\Enums\AdmissionRequestStatus;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\ParticipantRole;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\WardBoard;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\VitalSign;
use Modules\Clinical\Notifications\AdmissionDecidedNotification;
use Modules\Clinical\Notifications\AdmissionRequestedNotification;
use Modules\Clinical\Notifications\PatientTransferredWardNotification;
use Modules\Core\Enums\BedStatus;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WardBoardTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected User $doctor;

    protected User $nurse;

    protected User $charge;

    protected Location $ward;

    protected Location $bedA;

    protected Location $bedB;

    protected Location $bedC;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical']);

        config(['clinical.admissions.long_stay_days' => 7, 'clinical.discharge.enforce_readiness' => false]);

        $this->branch = Branch::factory()->default()->create();
        Role::findOrCreate('nurse', 'web');

        $this->doctor = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->nurse = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->nurse->assignRole('nurse');
        $this->charge = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);

        foreach (['View WardBoard', 'Update Encounter', 'View Encounter', 'discharge_patient', 'manage_bed_status'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->nurse->givePermissionTo(['View WardBoard', 'Update Encounter', 'View Encounter', 'discharge_patient', 'manage_bed_status']);
        $this->doctor->givePermissionTo(['View WardBoard', 'Update Encounter', 'View Encounter']);

        $this->ward = Location::factory()->room()->create(['branch_id' => $this->branch->id, 'name' => 'Male Medical', 'is_active' => true, 'nurse_in_charge_id' => $this->charge->id, 'capacity' => 3]);
        $this->bedA = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $this->ward->id, 'name' => 'Bed 1', 'is_active' => true]);
        $this->bedB = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $this->ward->id, 'name' => 'Bed 2', 'is_active' => true]);
        $this->bedC = Location::factory()->bed()->withStatus(BedStatus::BLOCKED)->create(['branch_id' => $this->branch->id, 'parent_id' => $this->ward->id, 'name' => 'Bed 3', 'is_active' => true, 'status_reason' => 'Mattress replacement']);

        $this->actingAs($this->nurse);
        session(['current_branch_id' => $this->branch->id]);
    }

    protected function patient(): Patient
    {
        return Patient::withoutEvents(fn () => Patient::factory()->male()->create(['branch_id' => $this->branch->id]));
    }

    public function test_board_maps_every_bed_with_status_patient_and_alerts(): void
    {
        $patient = $this->patient();
        $encounter = app(AdtService::class)->admit($patient, $this->bedA->id, actedBy: $this->doctor->id);
        $encounter->forceFill(['admitted_at' => now()->subDays(9)])->save();

        VitalSign::factory()->forPatient($patient)->create([
            'encounter_id' => $encounter->id,
            'systolic_bp' => 165,
            'diastolic_bp' => 100,
            'spo2' => 98,
            'recorded_at' => now()->subMinutes(10),
        ]);

        $board = app(WardBoardService::class)->buildWard($this->ward);

        $this->assertSame('Male Medical', $board['ward']['name']);
        $this->assertSame($this->charge->name, $board['ward']['nurse_in_charge']['name']);
        $this->assertCount(3, $board['beds']);

        $byName = collect($board['beds'])->keyBy('name');

        $this->assertSame('occupied', $byName['Bed 1']['status']);
        $this->assertSame($patient->full_name, $byName['Bed 1']['encounter']['patient']['name']);
        $this->assertSame(9, $byName['Bed 1']['encounter']['los_days']);
        $this->assertSame($this->doctor->name, $byName['Bed 1']['encounter']['attending']['name']);
        $this->assertNull($byName['Bed 1']['encounter']['nurse']);

        $alertKeys = array_column($byName['Bed 1']['encounter']['alerts'], 'key');
        $this->assertContains('abnormal_vitals', $alertKeys);
        $this->assertContains('long_stay', $alertKeys);
        $this->assertContains('no_nurse', $alertKeys);

        $this->assertSame('available', $byName['Bed 2']['status']);
        $this->assertNull($byName['Bed 2']['encounter']);
        $this->assertSame('blocked', $byName['Bed 3']['status']);
        $this->assertSame('Mattress replacement', $byName['Bed 3']['status_reason']);

        $this->assertSame(3, $board['stats']['beds_total']);
        $this->assertSame(1, $board['stats']['occupied']);
        $this->assertSame(1, $board['stats']['available']);
        $this->assertSame(1, $board['stats']['blocked']);
        $this->assertSame(33, $board['stats']['occupancy_pct']);
        $this->assertSame(1, $board['stats']['admissions_today']);
        $this->assertSame(1, $board['stats']['long_stay']);
    }

    public function test_board_shows_reservations_incoming_requests_and_discharges(): void
    {
        $waiting = $this->patient();
        $encounter = Encounter::factory()->forPatient($waiting)->outpatient()->create(['branch_id' => $this->branch->id, 'status' => EncounterStatus::ARRIVED]);
        $request = app(AdtService::class)->requestAdmission($encounter, $this->ward->id, bedId: $this->bedB->id, requestedBy: $this->doctor->id);

        $leaving = $this->patient();
        $stay = app(AdtService::class)->admit($leaving, $this->bedA->id);
        app(AdtService::class)->discharge($stay);

        $board = app(WardBoardService::class)->buildWard($this->ward);
        $byName = collect($board['beds'])->keyBy('name');

        $this->assertSame('reserved', $byName['Bed 2']['status']);
        $this->assertSame($waiting->full_name, $byName['Bed 2']['reserved_for']['patient_name']);
        $this->assertSame('cleaning', $byName['Bed 1']['status']);
        $this->assertCount(1, $board['incoming']);
        $this->assertSame($request->id, $board['incoming'][0]['id']);
        $this->assertSame(1, $board['stats']['pending_requests']);
        $this->assertSame(1, $board['stats']['discharges_today']);
    }

    public function test_page_requires_the_ward_board_permission(): void
    {
        $outsider = User::factory()->create(['branch_id' => $this->branch->id]);

        Livewire::actingAs($outsider)
            ->test(WardBoard::class)
            ->assertForbidden();
    }

    public function test_page_renders_the_selected_ward_and_switches_by_url(): void
    {
        $patient = $this->patient();
        app(AdtService::class)->admit($patient, $this->bedA->id);

        $other = Location::factory()->room()->create(['branch_id' => $this->branch->id, 'name' => 'Female Medical', 'is_active' => true]);
        Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $other->id, 'name' => 'Bed F1', 'is_active' => true]);

        Livewire::actingAs($this->nurse)
            ->test(WardBoard::class, ['wardId' => $this->ward->id])
            ->assertOk()
            ->assertSee('Male Medical')
            ->assertSee($patient->full_name)
            ->assertSee('Bed 3')
            ->assertSee('Mattress replacement')
            ->set('wardId', $other->id)
            ->assertSee('Bed F1')
            ->assertDontSee($patient->full_name);
    }

    public function test_bed_status_action_requires_permission_and_uses_the_state_machine(): void
    {
        Livewire::actingAs($this->doctor)
            ->test(WardBoard::class, ['wardId' => $this->ward->id])
            ->assertActionHidden('setBedStatus');

        Livewire::actingAs($this->nurse)
            ->test(WardBoard::class, ['wardId' => $this->ward->id])
            ->callAction('setBedStatus', data: ['status' => BedStatus::CLEANING->value, 'reason' => 'Spill'], arguments: ['bedId' => $this->bedB->id])
            ->assertNotified('Bed status updated');

        $this->assertSame(BedStatus::CLEANING, $this->bedB->fresh()->bedStatus());
        $this->assertSame('Spill', $this->bedB->fresh()->status_reason);
    }

    public function test_nurse_transfer_pass_and_discharge_actions_work_from_the_board(): void
    {
        Notification::fake();

        $patient = $this->patient();
        $encounter = app(AdtService::class)->admit($patient, $this->bedA->id, actedBy: $this->doctor->id);

        $page = Livewire::actingAs($this->nurse)->test(WardBoard::class, ['wardId' => $this->ward->id]);

        $page->callAction('assignNurse', data: ['user_id' => $this->nurse->id], arguments: ['encounterId' => $encounter->id])
            ->assertNotified('Nurse assigned');

        $this->assertSame(1, $encounter->fresh()->activeParticipants()->where('role', ParticipantRole::NURSE)->count());

        $page->callAction('setExpectedDischarge', data: ['expected_discharge_at' => now()->addDays(2)->toDateTimeString()], arguments: ['encounterId' => $encounter->id])
            ->assertNotified('Expected discharge updated');

        $this->assertNotNull($encounter->fresh()->expected_discharge_at);

        $page->callAction('transfer', data: ['ward_id' => $this->ward->id, 'bed_id' => $this->bedB->id], arguments: ['encounterId' => $encounter->id])
            ->assertNotified('Patient transferred');

        $this->assertSame($this->bedB->id, $encounter->fresh()->bed_id);

        $page->callAction('sendOnPass', data: ['reason' => 'Church'], arguments: ['encounterId' => $encounter->id])
            ->assertNotified('Patient sent on pass');

        $this->assertSame(EncounterStatus::ON_LEAVE, $encounter->fresh()->status);

        $page->callAction('returnFromPass', arguments: ['encounterId' => $encounter->id])
            ->assertNotified('Patient back on the ward');

        $page->callAction('discharge', data: ['discharge_disposition' => 'completed', 'notes' => 'Home'], arguments: ['encounterId' => $encounter->id])
            ->assertNotified('Patient discharged');

        $this->assertSame(EncounterStatus::FINISHED, $encounter->fresh()->status);
        $this->assertSame(BedStatus::CLEANING, $this->bedB->fresh()->bedStatus());
    }

    public function test_incoming_request_can_be_accepted_from_the_board(): void
    {
        $waiting = $this->patient();
        $encounter = Encounter::factory()->forPatient($waiting)->outpatient()->create(['branch_id' => $this->branch->id, 'status' => EncounterStatus::ARRIVED]);
        $request = app(AdtService::class)->requestAdmission($encounter, $this->ward->id, requestedBy: $this->doctor->id);

        Livewire::actingAs($this->nurse)
            ->test(WardBoard::class, ['wardId' => $this->ward->id])
            ->assertSee($waiting->full_name)
            ->callAction('acceptIncoming', data: ['bed_id' => $this->bedA->id], arguments: ['requestId' => $request->id])
            ->assertNotified('Admission accepted — patient admitted to bed');

        $this->assertSame(AdmissionRequestStatus::Accepted, $request->fresh()->status);
        $this->assertSame($this->bedA->id, $encounter->fresh()->bed_id);
    }

    public function test_actions_refuse_encounters_outside_the_current_branch(): void
    {
        $otherBranch = Branch::factory()->create();
        $foreignWard = Location::factory()->room()->create(['branch_id' => $otherBranch->id, 'is_active' => true]);
        $foreignBed = Location::factory()->bed()->create(['branch_id' => $otherBranch->id, 'parent_id' => $foreignWard->id, 'is_active' => true]);
        $foreignPatient = Patient::withoutEvents(fn () => Patient::factory()->male()->create(['branch_id' => $otherBranch->id]));
        $foreign = Encounter::factory()->forPatient($foreignPatient)->inpatient()->active()->create(['branch_id' => $otherBranch->id, 'bed_id' => $foreignBed->id]);

        Livewire::actingAs($this->nurse)
            ->test(WardBoard::class, ['wardId' => $this->ward->id])
            ->mountAction('discharge', arguments: ['encounterId' => $foreign->id])
            ->assertActionNotMounted('discharge')
            ->mountAction('transfer', arguments: ['encounterId' => $foreign->id])
            ->assertActionNotMounted('transfer');

        $this->assertNotSame(EncounterStatus::FINISHED, $foreign->fresh()->status);
    }

    public function test_ward_staff_are_notified_of_requests_decisions_and_transfers(): void
    {
        Notification::fake();

        $otherBranchNurse = User::factory()->create(['branch_id' => Branch::factory()->create()->id, 'is_active' => true]);
        $otherBranchNurse->assignRole('nurse');

        $waiting = $this->patient();
        $encounter = Encounter::factory()->forPatient($waiting)->outpatient()->create(['branch_id' => $this->branch->id, 'status' => EncounterStatus::ARRIVED]);
        $request = app(AdtService::class)->requestAdmission($encounter, $this->ward->id, requestedBy: $this->doctor->id);

        Notification::assertSentTo($this->nurse, AdmissionRequestedNotification::class, function (AdmissionRequestedNotification $notification, array $channels) {
            $payload = $notification->toDatabase($this->nurse);

            return in_array('database', $channels, true)
                && ! in_array('sms', $channels, true)
                && str_contains((string) $payload['title'], 'Male Medical');
        });
        Notification::assertSentTo($this->charge, AdmissionRequestedNotification::class);
        Notification::assertNotSentTo($otherBranchNurse, AdmissionRequestedNotification::class);
        Notification::assertNotSentTo($this->doctor, AdmissionRequestedNotification::class);

        app(AdtService::class)->rejectAdmission($request, 'Ward full', $this->nurse->id);

        Notification::assertSentTo($this->doctor, AdmissionDecidedNotification::class, fn (AdmissionDecidedNotification $n) => str_contains($n->toDatabase($this->doctor)['title'], 'rejected'));

        $other = Location::factory()->room()->create(['branch_id' => $this->branch->id, 'name' => 'Surgical', 'is_active' => true]);
        $otherBed = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $other->id, 'is_active' => true]);

        $admitted = app(AdtService::class)->admit($this->patient(), $this->bedA->id, actedBy: $this->doctor->id);
        app(AdtService::class)->transferInternal($admitted, $otherBed->id);

        Notification::assertSentTo($this->nurse, PatientTransferredWardNotification::class, fn (PatientTransferredWardNotification $n) => str_contains($n->toDatabase($this->nurse)['title'], 'Surgical'));

        $otherBed2 = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $other->id, 'is_active' => true]);
        app(AdtService::class)->transferInternal($admitted->fresh(), $otherBed2->id);

        Notification::assertSentToTimes($this->nurse, PatientTransferredWardNotification::class, 1, 'same-ward bed moves do not notify the ward');
    }

    public function test_expired_requests_notify_the_requester(): void
    {
        Notification::fake();

        $waiting = $this->patient();
        $encounter = Encounter::factory()->forPatient($waiting)->outpatient()->create(['branch_id' => $this->branch->id, 'status' => EncounterStatus::ARRIVED]);
        $request = app(AdtService::class)->requestAdmission($encounter, $this->ward->id, requestedBy: $this->doctor->id);
        $request->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->artisan('clinical:expire-admission-requests')->assertSuccessful();

        Notification::assertSentTo($this->doctor, AdmissionDecidedNotification::class, fn (AdmissionDecidedNotification $n) => str_contains($n->toDatabase($this->doctor)['title'], 'expired'));

        app(AdtService::class)->cancelAdmissionRequest(
            app(AdtService::class)->requestAdmission($encounter->fresh(), $this->ward->id, requestedBy: $this->doctor->id),
            'changed my mind',
            $this->doctor->id,
        );

        Notification::assertSentToTimes($this->doctor, AdmissionDecidedNotification::class, 1, 'withdrawing your own request does not notify you');
    }
}
