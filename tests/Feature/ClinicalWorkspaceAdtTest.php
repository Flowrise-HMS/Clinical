<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Classes\Services\BedAssignmentService;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Enums\AdtEventType;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClinicalWorkspaceAdtTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

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
        $this->nurse = User::factory()->create(['branch_id' => $this->branch->id]);
        Role::findOrCreate('nurse', 'web');
        $this->nurse->assignRole('nurse');

        foreach (['Create Encounter', 'Update Encounter', 'View Encounter'] as $permission) {
            Permission::findOrCreate($permission, 'web');
            $this->nurse->givePermissionTo($permission);
        }

        $this->patient = Patient::withoutEvents(
            fn () => Patient::factory()->create(['branch_id' => $this->branch->id])
        );

        $this->ward = Location::factory()->room()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->bedA = Location::factory()->bed()->create([
            'branch_id' => $this->branch->id,
            'parent_id' => $this->ward->id,
            'is_active' => true,
        ]);
        $this->bedB = Location::factory()->bed()->create([
            'branch_id' => $this->branch->id,
            'parent_id' => $this->ward->id,
            'is_active' => true,
        ]);
    }

    public function test_create_encounter_respects_selected_type(): void
    {
        $this->actingAs($this->nurse);

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->encounterFormData = [
            'type' => EncounterType::INPATIENT->value,
            'coverage_type' => 'none',
            'chief_complaint' => 'Chest pain',
        ];
        $page->createEncounter();

        $this->assertSame('adt', $page->activeTab);
        $this->assertDatabaseHas('encounters', [
            'patient_id' => $this->patient->id,
            'type' => EncounterType::INPATIENT->value,
            'chief_complaint' => 'Chest pain',
        ]);
    }

    public function test_admit_to_bed_updates_bed_and_creates_location_event(): void
    {
        $this->actingAs($this->nurse);

        $encounter = app(EncounterService::class)->createForPatient(
            $this->patient,
            EncounterType::INPATIENT,
            chiefComplaint: 'Admit me',
            createdBy: $this->nurse->id,
        );

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->adtFormData = [
            'ward_id' => $this->ward->id,
            'bed_id' => $this->bedA->id,
            'notes' => 'Assigned',
        ];
        $page->admitToBed();

        $encounter->refresh();
        $this->assertSame($this->bedA->id, $encounter->bed_id);
        $this->assertSame(EncounterStatus::ARRIVED, $encounter->status);
        $this->assertDatabaseHas('encounter_location_events', [
            'encounter_id' => $encounter->id,
            'event_type' => AdtEventType::Admitted->value,
            'to_bed_id' => $this->bedA->id,
        ]);
    }

    public function test_transfer_internal_updates_bed_via_workspace(): void
    {
        $this->actingAs($this->nurse);

        $encounter = app(AdtService::class)->admit($this->patient, $this->bedA->id);
        $encounter->update(['status' => EncounterStatus::IN_PROGRESS]);

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->adtFormData = array_merge($page->adtFormData, [
            'transfer_ward_id' => $this->ward->id,
            'transfer_bed_id' => $this->bedB->id,
        ]);
        $page->transferInternal();

        $this->assertSame($this->bedB->id, $encounter->fresh()->bed_id);
        $this->assertDatabaseHas('encounter_location_events', [
            'encounter_id' => $encounter->id,
            'event_type' => AdtEventType::TransferredInternal->value,
            'to_bed_id' => $this->bedB->id,
        ]);
    }

    public function test_adt_form_defaults_include_transfer_in_ward_and_bed_keys(): void
    {
        $page = $this->makeWorkspacePage();

        foreach ([
            'ward_id',
            'bed_id',
            'notes',
            'transfer_ward_id',
            'transfer_bed_id',
            'transfer_in_ward_id',
            'transfer_in_bed_id',
            'transfer_in_chief_complaint',
            'source_label',
            'from_branch_id',
            'destination_branch_id',
            'destination_label',
        ] as $key) {
            $this->assertArrayHasKey($key, $page->adtFormData);
            $this->assertNull($page->adtFormData[$key]);
        }
    }

    public function test_transfer_in_admits_patient_when_ward_and_bed_are_set(): void
    {
        $this->actingAs($this->nurse);

        $page = $this->makeWorkspacePage();
        $page->selectPatient($this->patient->id);
        $page->adtFormData = array_merge($page->adtFormData, [
            'admission_source' => 'local',
            'transfer_in_ward_id' => $this->ward->id,
            'transfer_in_bed_id' => $this->bedA->id,
            'transfer_in_chief_complaint' => 'Fever',
            'transfer_in_notes' => 'Walk-in admission',
        ]);
        $page->transferIn();

        $this->assertDatabaseHas('encounters', [
            'patient_id' => $this->patient->id,
            'bed_id' => $this->bedA->id,
            'chief_complaint' => 'Fever',
        ]);
        $this->assertDatabaseHas('encounter_location_events', [
            'event_type' => AdtEventType::TransferredIn->value,
            'to_bed_id' => $this->bedA->id,
        ]);
        $this->assertNull($page->adtFormData['transfer_in_ward_id']);
        $this->assertNull($page->adtFormData['transfer_in_bed_id']);
    }

    public function test_get_available_beds_excludes_occupied(): void
    {
        $this->actingAs($this->nurse);

        app(AdtService::class)->admit($this->patient, $this->bedA->id);

        $available = app(BedAssignmentService::class)->getAvailableBeds($this->ward->id);

        $this->assertFalse($available->has($this->bedA->id));
        $this->assertTrue($available->has($this->bedB->id));
    }

    protected function makeWorkspacePage(): ClinicalWorkspace
    {
        $page = app(ClinicalWorkspace::class);
        $page->boot();

        return $page;
    }
}
