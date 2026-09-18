<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Classes\Services\DischargeReadinessService;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Exceptions\DischargeBlockedException;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\DischargeSummary;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Classes\Services\BedStatusService;
use Modules\Core\Enums\ServiceCategoryCode;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceCategory;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DischargeReadinessTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected User $doctor;

    protected Patient $patient;

    protected Location $bed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical']);

        config([
            'clinical.discharge.enforce_readiness' => true,
            'clinical.discharge.require_signed_summary' => true,
        ]);

        $this->branch = Branch::factory()->default()->create();
        $this->doctor = User::factory()->create(['branch_id' => $this->branch->id]);
        foreach (['View ClinicalWorkspace', 'Create Encounter', 'Update Encounter', 'View Encounter', 'ViewAny Patient', 'View Patient', 'discharge_patient', 'sign_discharge_summary'] as $permission) {
            Permission::findOrCreate($permission, 'web');
            $this->doctor->givePermissionTo($permission);
        }
        $this->actingAs($this->doctor);
        session(['current_branch_id' => $this->branch->id]);

        $this->patient = Patient::withoutEvents(fn () => Patient::factory()->male()->create(['branch_id' => $this->branch->id]));
        $ward = Location::factory()->room()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->bed = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $ward->id, 'is_active' => true]);
    }

    protected function admitted(): Encounter
    {
        return app(AdtService::class)->admit($this->patient, $this->bed->id);
    }

    protected function makeReady(Encounter $encounter): void
    {
        EncounterDiagnosis::factory()->create(['encounter_id' => $encounter->id, 'patient_id' => $this->patient->id]);
        DischargeSummary::factory()->signed()->create(['encounter_id' => $encounter->id, 'patient_id' => $this->patient->id]);
    }

    public function test_fresh_admission_is_blocked_on_diagnosis_and_summary(): void
    {
        $readiness = app(DischargeReadinessService::class)->assess($this->admitted());

        $this->assertFalse($readiness->isReady());
        $this->assertEqualsCanonicalizing(['discharge_diagnosis', 'discharge_summary_signed'], array_column($readiness->blocking(), 'key'));
        $this->assertContains('follow_up_booked', array_column($readiness->items, 'key'));
        $this->assertSame('info', collect($readiness->items)->firstWhere('key', 'follow_up_booked')['severity']);
    }

    public function test_pending_lab_blocks_but_other_pending_orders_do_not(): void
    {
        $encounter = $this->admitted();
        $this->makeReady($encounter);

        $lab = Service::factory()->create(['branch_id' => $this->branch->id, 'requires_payment_before' => false, 'category_id' => ServiceCategory::factory()->create(['code' => ServiceCategoryCode::LAB])->id]);
        $procedure = Service::factory()->create(['branch_id' => $this->branch->id, 'requires_payment_before' => false, 'category_id' => ServiceCategory::factory()->create(['code' => ServiceCategoryCode::PRO])->id]);
        $request = ServiceRequest::factory()->create(['patient_id' => $this->patient->id, 'encounter_id' => $encounter->id, 'branch_id' => $this->branch->id]);
        $labItem = RequestItem::factory()->create(['service_request_id' => $request->id, 'service_id' => $lab->id, 'status' => 'pending']);
        RequestItem::factory()->create(['service_request_id' => $request->id, 'service_id' => $procedure->id, 'status' => 'pending']);

        $readiness = app(DischargeReadinessService::class)->assess($encounter);

        $this->assertSame(['pending_diagnostics'], array_column($readiness->blocking(), 'key'));

        $labItem->update(['status' => 'completed']);

        $this->assertTrue(app(DischargeReadinessService::class)->assess($encounter)->isReady());
    }

    public function test_severity_comes_from_config_and_unsigned_notes_only_warn(): void
    {
        $encounter = $this->admitted();
        $this->makeReady($encounter);
        ClinicalNote::factory()->forPatient($this->patient)->forEncounter($encounter)->create(['author_id' => $this->doctor->id]);

        $readiness = app(DischargeReadinessService::class)->assess($encounter);

        $this->assertTrue($readiness->isReady());
        $this->assertSame(['unsigned_notes'], array_column($readiness->warnings(), 'key'));

        config(['clinical.discharge.readiness.unsigned_notes' => 'blocking']);

        $this->assertFalse(app(DischargeReadinessService::class)->assess($encounter)->isReady());
    }

    public function test_discharge_is_blocked_until_ready_or_overridden(): void
    {
        $encounter = $this->admitted();

        try {
            app(AdtService::class)->discharge($encounter);
            $this->fail('Discharge went through with blocking items');
        } catch (DischargeBlockedException $e) {
            $this->assertContains('discharge_diagnosis', array_column($e->readiness->blocking(), 'key'));
            $this->assertSame(EncounterStatus::IN_PROGRESS, $encounter->fresh()->status);
        }

        $discharged = app(AdtService::class)->discharge($encounter->fresh(), overrideReason: 'Patient insists on leaving; counselled');

        $this->assertSame(EncounterStatus::FINISHED, $discharged->status);
        $this->assertSame('Patient insists on leaving; counselled', $discharged->metadata['discharge_override']['reason']);
        $this->assertContains('discharge_summary_signed', $discharged->metadata['discharge_override']['items']);
    }

    public function test_ready_encounter_discharges_without_override_and_records_follow_up(): void
    {
        $encounter = $this->admitted();
        $this->makeReady($encounter);

        $discharged = app(AdtService::class)->discharge($encounter->fresh(), followUpAt: now()->addWeek()->startOfHour());

        $this->assertSame(EncounterStatus::FINISHED, $discharged->status);
        $this->assertArrayNotHasKey('discharge_override', $discharged->metadata);
        $this->assertNotNull($discharged->metadata['follow_up']['at']);
        $this->assertNotNull($discharged->dischargeSummary?->follow_up_at);
    }

    public function test_transfers_out_and_deaths_are_exempt_from_blocking(): void
    {
        $first = $this->admitted();
        $out = app(AdtService::class)->discharge($first, DischargeDisposition::DECEASED);
        $this->assertSame(EncounterStatus::FINISHED, $out->status);
        $this->assertStringContainsString('Exempt', $out->metadata['discharge_override']['reason']);

        app(BedStatusService::class)->markAvailable($this->bed->fresh());
        $second = app(AdtService::class)->admit($this->patient, $this->bed->id);
        $transferred = app(AdtService::class)->discharge($second, DischargeDisposition::TRANSFERRED, 'Korle Bu');
        $this->assertSame(EncounterStatus::FINISHED, $transferred->status);
    }

    public function test_enforcement_can_be_switched_off(): void
    {
        config(['clinical.discharge.enforce_readiness' => false]);

        $discharged = app(AdtService::class)->discharge($this->admitted());

        $this->assertSame(EncounterStatus::FINISHED, $discharged->status);
        $this->assertArrayNotHasKey('discharge_override', $discharged->metadata);
    }

    public function test_workspace_discharge_shows_the_checklist_and_requires_an_override_reason(): void
    {
        $encounter = $this->admitted();

        $page = Livewire::actingAs($this->doctor)
            ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
            ->set('activeTab', 'adt');

        $page->assertSee('block discharge')
            ->assertSee('Discharge summary signed');

        $page->set('dischargeData.discharge_disposition', 'completed')
            ->set('dischargeData.override_reason', null)
            ->call('saveDischarge')
            ->assertHasErrors(['dischargeData.override_reason']);

        $this->assertSame(EncounterStatus::IN_PROGRESS, $encounter->fresh()->status);

        $page->set('dischargeData.override_reason', 'Clinically appropriate; summary to follow')
            ->call('saveDischarge')
            ->assertHasNoErrors()
            ->assertNotified('Patient discharged');

        $this->assertSame(EncounterStatus::FINISHED, $encounter->fresh()->status);
    }
}
