<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Classes\Services\DischargeSummaryService;
use Modules\Clinical\Classes\Services\Pdf\DischargeSummaryPdfService;
use Modules\Clinical\Enums\DiagnosisType;
use Modules\Clinical\Enums\DischargeSummaryStatus;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DischargeSummaryTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected User $doctor;

    protected User $nurse;

    protected Patient $patient;

    protected Encounter $encounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical']);
        config(['clinical.discharge.enforce_readiness' => false]);

        $this->branch = Branch::factory()->default()->create();
        $this->doctor = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->nurse = User::factory()->create(['branch_id' => $this->branch->id]);
        foreach (['View ClinicalWorkspace', 'Create Encounter', 'Update Encounter', 'View Encounter', 'ViewAny Patient', 'View Patient', 'sign_discharge_summary', 'print_discharge_summary'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->doctor->givePermissionTo(['View ClinicalWorkspace', 'Create Encounter', 'Update Encounter', 'View Encounter', 'ViewAny Patient', 'View Patient', 'sign_discharge_summary', 'print_discharge_summary']);
        $this->nurse->givePermissionTo(['View ClinicalWorkspace', 'Update Encounter', 'View Encounter', 'ViewAny Patient', 'View Patient']);
        $this->actingAs($this->doctor);
        session(['current_branch_id' => $this->branch->id]);

        $this->patient = Patient::withoutEvents(fn () => Patient::factory()->male()->create(['branch_id' => $this->branch->id]));
        $ward = Location::factory()->room()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $bed = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $ward->id, 'is_active' => true]);
        $this->encounter = app(AdtService::class)->admit($this->patient, $bed->id, chiefComplaint: 'Fever and rigors');

        EncounterDiagnosis::factory()->create(['encounter_id' => $this->encounter->id, 'patient_id' => $this->patient->id, 'description' => 'Severe malaria', 'icd10_code' => 'B50.9', 'type' => DiagnosisType::Primary]);
    }

    public function test_draft_is_prefilled_from_the_encounter(): void
    {
        $summary = app(DischargeSummaryService::class)->draftFor($this->encounter, $this->doctor->id);

        $this->assertSame(DischargeSummaryStatus::DRAFT, $summary->status);
        $this->assertSame('Fever and rigors', $summary->presenting_complaint);
        $this->assertSame('Severe malaria', $summary->admission_diagnosis);
        $this->assertSame('B50.9', $summary->discharge_diagnoses[0]['icd10_code']);
        $this->assertSame($this->doctor->id, $summary->authored_by);
        $this->assertSame($summary->id, app(DischargeSummaryService::class)->draftFor($this->encounter)->id, 'draftFor is idempotent');
    }

    public function test_sign_requires_permission_locks_the_summary_and_writes_a_discharge_note(): void
    {
        $service = app(DischargeSummaryService::class);
        $summary = $service->draftFor($this->encounter, $this->doctor->id);
        $service->update($summary, ['hospital_course' => 'Treated with IV artesunate, improved by day 3.', 'instructions' => 'Complete oral course.']);

        try {
            $service->sign($summary->fresh(), $this->nurse);
            $this->fail('Nurse signed a discharge summary');
        } catch (\InvalidArgumentException) {
            $this->assertSame(DischargeSummaryStatus::DRAFT, $summary->fresh()->status);
        }

        $signed = $service->sign($summary->fresh(), $this->doctor);

        $this->assertSame(DischargeSummaryStatus::SIGNED, $signed->status);
        $this->assertSame($this->doctor->id, $signed->signed_by);
        $this->assertTrue(ClinicalNote::query()->where('encounter_id', $this->encounter->id)->where('note_type', NoteType::DISCHARGE->value)->exists());

        $this->expectException(\InvalidArgumentException::class);
        $service->update($signed, ['instructions' => 'changed']);
    }

    public function test_amending_a_signed_summary_keeps_an_audit_trail(): void
    {
        $service = app(DischargeSummaryService::class);
        $summary = $service->sign($service->draftFor($this->encounter, $this->doctor->id), $this->doctor);

        $amended = $service->amend($summary, ['instructions' => 'Return if fever recurs.'], $this->doctor, 'Missed instruction');

        $this->assertSame(DischargeSummaryStatus::AMENDED, $amended->status);
        $this->assertSame('Return if fever recurs.', $amended->instructions);
        $this->assertSame('Missed instruction', $amended->metadata['amendments'][0]['reason']);
        $this->assertSame(['instructions'], $amended->metadata['amendments'][0]['changed']);
    }

    public function test_pdf_route_is_permission_gated_and_renders(): void
    {
        $summary = app(DischargeSummaryService::class)->draftFor($this->encounter, $this->doctor->id);

        $this->actingAs($this->nurse)
            ->get(route('clinical.discharge-summaries.pdf', $summary))
            ->assertForbidden();

        $response = $this->actingAs($this->doctor)->get(route('clinical.discharge-summaries.pdf', $summary));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('discharge-summary-'.$this->patient->mrn, (string) $response->headers->get('Content-Disposition'));

        $html = view('clinical::pdf.discharge-summary', ['summary' => app(DischargeSummaryPdfService::class)->prepare($summary)])->render();

        $this->assertStringContainsString('DRAFT', $html);
        $this->assertStringContainsString('Severe malaria', $html);
        $this->assertStringContainsString($this->patient->full_name, $html);
    }

    public function test_workspace_exposes_the_summary_actions_on_the_adt_tab(): void
    {
        $page = Livewire::actingAs($this->doctor)
            ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
            ->set('activeTab', 'adt')
            ->assertSee('Discharge planning')
            ->assertActionVisible('discharge_summary')
            ->assertActionHidden('signDischargeSummary')
            ->assertActionHidden('print_discharge_summary');

        $page->callAction('discharge_summary', data: [
            'presenting_complaint' => 'Fever and rigors',
            'hospital_course' => 'Improved on artesunate.',
            'condition_at_discharge' => 'improved',
            'discharge_diagnoses' => [['description' => 'Severe malaria', 'icd10_code' => 'B50.9', 'type' => 'primary']],
            'discharge_medications' => [['drug' => 'Artemether-lumefantrine', 'dose' => '4 tabs', 'frequency' => 'BID', 'duration_days' => 3]],
            'instructions' => 'Complete the course.',
        ])->assertNotified('Discharge summary saved');

        $summary = $this->encounter->fresh()->dischargeSummary;
        $this->assertSame('Improved on artesunate.', strip_tags($summary->hospital_course));
        $this->assertSame('Artemether-lumefantrine', $summary->discharge_medications[0]['drug']);

        // A fresh component instance, as a real browser would send for the next action.
        Livewire::actingAs($this->doctor)
            ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
            ->set('activeTab', 'adt')
            ->assertActionVisible('signDischargeSummary')
            ->assertActionVisible('print_discharge_summary')
            ->callAction('signDischargeSummary')
            ->assertNotified('Discharge summary signed');

        $this->assertTrue($summary->fresh()->isSigned());
    }

    public function test_expected_discharge_and_long_stay(): void
    {
        config(['clinical.admissions.long_stay_days' => 5]);

        $this->assertFalse($this->encounter->fresh()->isLongStay());

        $updated = app(AdtService::class)->setExpectedDischarge($this->encounter, now()->addDays(2), $this->doctor->id, 'Awaiting culture');
        $this->assertNotNull($updated->expected_discharge_at);
        $this->assertSame('Awaiting culture', $updated->metadata['expected_discharge_history'][0]['reason']);
        $this->assertFalse($updated->isLongStay());

        $updated->forceFill(['expected_discharge_at' => now()->subDay()])->save();
        $this->assertTrue($updated->fresh()->isLongStay(), 'past the expected date counts as a long stay');

        $updated->forceFill(['expected_discharge_at' => null, 'admitted_at' => now()->subDays(6)])->save();
        $this->assertTrue($updated->fresh()->isLongStay(), 'beyond the threshold counts as a long stay');
        $this->assertSame(1, Encounter::query()->longStay()->count());

        Livewire::actingAs($this->doctor)
            ->test(\Modules\Clinical\Filament\Widgets\LongStayPatientsWidget::class)
            ->assertSee($this->patient->full_name);
    }
}
