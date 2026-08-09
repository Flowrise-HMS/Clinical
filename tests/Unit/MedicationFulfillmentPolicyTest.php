<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\MedicationRoute;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy']);

    $this->branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
});

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

it('accepts MedicationRoute enums when resolving default administration context', function (): void {
    $encounter = Encounter::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'type' => EncounterType::OUTPATIENT,
        'status' => 'in_progress',
        'admitted_at' => now()->subHour(),
    ]);

    $policy = app(MedicationFulfillmentPolicy::class);

    expect($policy->defaultAdministrationContext($encounter, MedicationRoute::IV))
        ->toBe(AdministrationContext::IN_FACILITY)
        ->and($policy->defaultAdministrationContext($encounter, MedicationRoute::PO))
        ->toBe(AdministrationContext::TAKE_HOME)
        ->and($policy->defaultAdministrationContext($encounter, 'im'))
        ->toBe(AdministrationContext::IN_FACILITY);
});

it('loads prescription detail without lazy loading when checking mar eligibility', function (): void {
    $clinician = User::factory()->create(['branch_id' => $this->branch->id]);
    Permission::findOrCreate('administer_medication', 'web');
    $clinician->givePermissionTo('administer_medication');

    $encounter = Encounter::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'type' => EncounterType::INPATIENT,
        'status' => 'in_progress',
        'admitted_at' => now()->subHour(),
    ]);

    $service = Service::factory()->create();
    $request = ServiceRequest::factory()->create([
        'patient_id' => $this->patient->id,
        'encounter_id' => $encounter->id,
        'branch_id' => $this->branch->id,
        'ordered_by' => $clinician->id,
    ]);

    $item = RequestItem::factory()->create([
        'service_request_id' => $request->id,
        'service_id' => $service->id,
        'status' => RequestItemStatus::PENDING,
    ]);

    PrescriptionDetail::factory()->create([
        'request_item_id' => $item->id,
        'administration_context' => AdministrationContext::IN_FACILITY,
        'total_administrations' => 2,
        'course_end_at' => now()->addDays(2),
    ]);

    $freshItem = RequestItem::query()->findOrFail($item->id);

    Model::preventLazyLoading();

    $canRecord = app(MedicationFulfillmentPolicy::class)->canRecordMar($freshItem, $clinician);

    expect($canRecord)->toBeTrue();
});
