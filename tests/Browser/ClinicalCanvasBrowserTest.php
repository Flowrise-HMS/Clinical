<?php

namespace Modules\Clinical\Tests\Browser;

use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\MedicationCanvas;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\Timeline;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Classes\Services\PrescriptionScheduleCalculator;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Tests\TestCase;

uses(TestCase::class);

it('renders the timeline canvas without JavaScript errors', function () {
    $this->migrateModules();
    $this->seed(ShieldSeeder::class);

    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    $doctor->assignRole('super_admin');

    $patient = Patient::factory()->create(['branch_id' => $branch->id]);

    $encounter = Encounter::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => 'in_progress',
        'type' => 'inpatient',
        'created_by' => $doctor->id,
        'admitted_at' => now()->subDay(),
    ]);

    VitalSign::factory()->forPatient($patient)->create([
        'encounter_id' => $encounter->id,
        'recorded_by' => $doctor->id,
        'recorded_at' => now()->subHours(20),
    ]);

    ClinicalNote::factory()->forPatient($patient)->create([
        'encounter_id' => $encounter->id,
        'author_id' => $doctor->id,
        'created_at' => now()->subHours(2),
    ]);

    $this->actingAs($doctor);

    $page = visit(Timeline::getUrl(['patient' => $patient->id, 'view' => 'canvas']));

    $page->assertNoJavaScriptErrors()
        ->assertSee($patient->full_name)
        ->assertSee('Vitals')
        ->assertSee('Notes')
        ->assertPresent('[data-clinical-canvas]')
        ->assertPresent('[data-node-id="patient_'.$patient->id.'"]')
        ->assertPresent('[data-node-id="encounter_'.$encounter->id.'"]');
});

it('renders the medication canvas without JavaScript errors', function () {
    $this->migrateModules();
    $this->seed(ShieldSeeder::class);

    $branch = Branch::factory()->create();
    $nurse = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    $nurse->assignRole('super_admin');

    $patient = Patient::factory()->create(['branch_id' => $branch->id]);

    $encounter = Encounter::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => 'in_progress',
        'type' => 'inpatient',
        'created_by' => $nurse->id,
    ]);

    $service = Service::factory()->create([
        'branch_id' => $branch->id,
        'category_id' => $this->medicationServiceCategory()->id,
        'requires_payment_before' => false,
    ]);
    Medication::factory()->create(['service_id' => $service->id]);

    $request = ServiceRequest::factory()->create([
        'patient_id' => $patient->id,
        'encounter_id' => $encounter->id,
        'branch_id' => $branch->id,
    ]);

    $item = RequestItem::factory()->create([
        'service_request_id' => $request->id,
        'service_id' => $service->id,
        'status' => 'pending',
    ]);

    PrescriptionDetail::create([
        'request_item_id' => $item->id,
        'frequency' => MedicationFrequency::BID->value,
        'duration_days' => 2,
        'route' => 'po',
        'dose_amount' => 1,
        'administration_context' => AdministrationContext::IN_FACILITY,
        'course_started_at' => now(),
        'course_end_at' => now()->addDays(2),
        'total_administrations' => app(PrescriptionScheduleCalculator::class)->compute([
            'frequency' => MedicationFrequency::BID->value,
            'duration_days' => 2,
            'prn' => false,
            'course_started_at' => now(),
        ])['total_administrations'],
    ]);

    $this->actingAs($nurse);

    $page = visit(MedicationCanvas::getUrl(['patient' => $patient->id]));

    $page->assertNoJavaScriptErrors()
        ->assertSee($patient->full_name)
        ->assertPresent('[data-clinical-canvas]')
        ->assertPresent('[data-node-id="rx_'.$item->id.'"]')
        ->assertSee('Next dose')
        ->assertSee('Record dose');
});
