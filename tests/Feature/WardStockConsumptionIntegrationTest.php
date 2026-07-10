<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Classes\Services\MedicationAdministrationService;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Classes\Support\NullWardMedicationConsumption;
use Modules\Core\Contracts\WardMedicationConsumptionContract;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\Core\Models\Location;
use Modules\Core\Models\Service;
use Modules\Inventory\Classes\Services\IssueToWardService;
use Modules\Inventory\Classes\Services\RequisitionService;
use Modules\Inventory\Classes\Services\StockLedgerService;
use Modules\Inventory\Enums\StockLocationType;
use Modules\Inventory\Enums\TransactionType;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockBalance;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Classes\Services\PrescriptionScheduleCalculator;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WardStockConsumptionIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireModule('Inventory');
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy', 'Inventory']);

        config(['clinical.mar_payment.require_before_mar' => false]);
    }

    private function createDepartmentForBranch(Branch $branch): Department
    {
        $department = Department::factory()->create();
        $location = Location::factory()->create(['branch_id' => $branch->id]);
        $department->locations()->attach($location->id, ['is_primary' => true]);

        return $department;
    }

    public function test_mar_dose_consumes_linked_ward_inventory_stock(): void
    {
        $branch = Branch::factory()->create();
        $department = $this->createDepartmentForBranch($branch);
        $patient = Patient::factory()->create();
        $nurse = User::factory()->create(['branch_id' => $branch->id]);
        Permission::findOrCreate('administer_medication', 'web');
        $nurse->givePermissionTo('administer_medication');
        $this->actingAs($nurse);

        $encounter = Encounter::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'type' => EncounterType::INPATIENT,
            'status' => EncounterStatus::IN_PROGRESS,
        ]);

        $service = Service::factory()->create([
            'category_id' => $this->medicationServiceCategory()->id,
            'requires_payment_before' => false,
        ]);
        $medication = Medication::factory()->create(['service_id' => $service->id]);
        $inventoryItem = InventoryItem::factory()->create(['medication_id' => $medication->id]);

        app(StockLedgerService::class)->lockAndIncrement(
            itemId: $inventoryItem->id,
            branchId: $branch->id,
            locationType: StockLocationType::Dispensary,
            departmentId: null,
            stockTransferId: null,
            qty: 20,
            transactionType: TransactionType::Receive,
            reference: null,
        );

        $requisition = app(RequisitionService::class)->create([
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'items' => [
                ['inventory_item_id' => $inventoryItem->id, 'quantity_requested' => 10],
            ],
        ]);

        app(RequisitionService::class)->approve($requisition);
        app(IssueToWardService::class)->issue($requisition->items->first(), 10);

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
            'frequency' => MedicationFrequency::STAT->value,
            'duration_days' => 1,
            'route' => 'po',
            'dose_amount' => 1,
            'administration_context' => AdministrationContext::IN_FACILITY,
            'course_started_at' => now(),
            'course_end_at' => now()->addDay(),
            'total_administrations' => null,
        ]);

        app(MedicationAdministrationService::class)->administer($item->fresh(), [
            'status' => MedicationAdministrationStatus::GIVEN->value,
            'quantity_given' => 1,
        ], null, $nurse);

        $this->assertSame(9, (int) StockBalance::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('branch_id', $branch->id)
            ->where('location_type', StockLocationType::Ward)
            ->where('department_id', $department->id)
            ->value('quantity_on_hand'));
    }

    public function test_mar_still_records_when_inventory_consumption_is_null_bound(): void
    {
        $this->app->bind(WardMedicationConsumptionContract::class, NullWardMedicationConsumption::class);

        $branch = Branch::factory()->create();
        $department = $this->createDepartmentForBranch($branch);
        $patient = Patient::factory()->create();
        $nurse = User::factory()->create(['branch_id' => $branch->id]);
        Permission::findOrCreate('administer_medication', 'web');
        $nurse->givePermissionTo('administer_medication');
        $this->actingAs($nurse);

        $encounter = Encounter::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'type' => EncounterType::INPATIENT,
            'status' => EncounterStatus::IN_PROGRESS,
        ]);

        $service = Service::factory()->create([
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
            'frequency' => MedicationFrequency::STAT->value,
            'duration_days' => 1,
            'route' => 'po',
            'dose_amount' => 1,
            'administration_context' => AdministrationContext::IN_FACILITY,
            'course_started_at' => now(),
            'course_end_at' => now()->addDay(),
            'total_administrations' => app(PrescriptionScheduleCalculator::class)
                ->compute([
                    'frequency' => MedicationFrequency::STAT->value,
                    'duration_days' => 1,
                    'prn' => false,
                    'course_started_at' => now(),
                ])['total_administrations'],
        ]);

        app(MedicationAdministrationService::class)->administer($item->fresh(), [
            'status' => MedicationAdministrationStatus::GIVEN->value,
            'quantity_given' => 1,
        ], null, $nurse);

        $this->assertDatabaseHas('medication_administrations', [
            'request_item_id' => $item->id,
            'status' => MedicationAdministrationStatus::GIVEN->value,
            'quantity_given' => 1,
        ]);
    }
}
