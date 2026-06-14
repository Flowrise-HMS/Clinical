<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Clinical\Classes\Services\MedicationAdministrationService;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceCategory;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Classes\Services\DispenseService;
use Modules\Pharmacy\Classes\Services\MedicationOrderService;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\ControlledSchedule;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Modules\Pharmacy\Models\StockItem;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MedicationAdministrationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('module:migrate', ['module' => 'Core', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Patient', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Billing', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Clinical', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Pharmacy', '--force' => true]);

        config(['clinical.mar_payment.require_before_mar' => false]);
    }

    public function test_qid_three_day_order_computes_twelve_doses_and_completes_after_twelfth_given(): void
    {
        [$item, $nurse, $detail] = $this->seedInFacilityMarOrder(MedicationFrequency::QID, 3);

        $this->assertSame(12, $detail->total_administrations);

        for ($i = 1; $i <= 11; $i++) {
            app(MedicationAdministrationService::class)->administer($item->fresh(), [
                'status' => MedicationAdministrationStatus::GIVEN->value,
                'quantity_given' => 1,
                'started_at' => now()->addMinutes($i),
            ], null, $nurse);
            $item->refresh();
            $this->assertTrue($item->isInProgress());
        }

        app(MedicationAdministrationService::class)->administer($item->fresh(), [
            'status' => MedicationAdministrationStatus::GIVEN->value,
            'quantity_given' => 1,
            'started_at' => now()->addHours(1),
        ], null, $nurse);

        $this->assertTrue($item->fresh()->isCompleted());
    }

    public function test_take_home_order_rejects_mar(): void
    {
        [$item, , $medication, $pharmacist] = $this->seedTakeHomeOrder();
        $nurse = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        app(MedicationAdministrationService::class)->administer($item, [
            'status' => MedicationAdministrationStatus::GIVEN->value,
        ], null, $nurse);
    }

    public function test_take_home_order_completes_on_dispense(): void
    {
        [$item, , $medication, $pharmacist] = $this->seedTakeHomeOrder();

        StockItem::factory()->create([
            'branch_id' => $item->serviceRequest->branch_id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 10,
        ]);

        app(DispenseService::class)->dispense($item->fresh(), [
            'medication_id' => $medication->id,
            'quantity' => 1,
        ], $pharmacist);

        $this->assertTrue($item->fresh()->isCompleted());
    }

    public function test_in_facility_supply_dispense_does_not_complete_order(): void
    {
        [$item, , , $medication] = $this->seedInFacilityMarOrder(MedicationFrequency::BID, 2);
        $pharmacist = User::factory()->create(['branch_id' => $item->serviceRequest->branch_id]);
        Role::findOrCreate('pharmacist', 'web');
        $pharmacist->assignRole('pharmacist');

        StockItem::factory()->create([
            'branch_id' => $item->serviceRequest->branch_id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 10,
        ]);

        app(DispenseService::class)->dispense($item->fresh(), [
            'medication_id' => $medication->id,
            'quantity' => 1,
        ], $pharmacist);

        $this->assertTrue($item->fresh()->isInProgress());
    }

    public function test_emergency_encounter_allows_unpaid_mar(): void
    {
        config(['clinical.mar_payment.require_before_mar' => true]);

        [$item, $nurse] = $this->seedInFacilityMarOrder(
            MedicationFrequency::STAT,
            1,
            requiresPayment: true,
            encounterType: EncounterType::EMERGENCY,
            paid: false,
        );

        app(MedicationAdministrationService::class)->administer($item, [
            'status' => MedicationAdministrationStatus::GIVEN->value,
        ], null, $nurse);

        $this->assertDatabaseHas('medication_administrations', [
            'request_item_id' => $item->id,
            'status' => MedicationAdministrationStatus::GIVEN->value,
        ]);
    }

    public function test_non_emergency_unpaid_mar_is_blocked(): void
    {
        config(['clinical.mar_payment.require_before_mar' => true]);

        [$item, $nurse] = $this->seedInFacilityMarOrder(
            MedicationFrequency::STAT,
            1,
            requiresPayment: true,
            encounterType: EncounterType::OUTPATIENT,
            paid: false,
        );

        $this->expectException(\InvalidArgumentException::class);
        app(MedicationAdministrationService::class)->administer($item, [
            'status' => MedicationAdministrationStatus::GIVEN->value,
        ], null, $nurse);
    }

    public function test_controlled_medication_rejects_without_witness_toggle(): void
    {
        [$item, $nurse, , $medication] = $this->seedInFacilityMarOrder(MedicationFrequency::STAT, 1);
        $medication->update(['controlled_schedule' => ControlledSchedule::SCHEDULE_2]);

        $this->expectException(\InvalidArgumentException::class);
        app(MedicationAdministrationService::class)->administer($item->fresh(), [
            'status' => MedicationAdministrationStatus::GIVEN->value,
            'witness_confirmed' => false,
        ], null, $nurse);
    }

    public function test_controlled_medication_saves_with_witness_toggle(): void
    {
        [$item, $nurse, , $medication] = $this->seedInFacilityMarOrder(MedicationFrequency::STAT, 1);
        $medication->update(['controlled_schedule' => ControlledSchedule::SCHEDULE_2]);

        app(MedicationAdministrationService::class)->administer($item->fresh(), [
            'status' => MedicationAdministrationStatus::GIVEN->value,
            'witness_confirmed' => true,
        ], null, $nurse);

        $this->assertDatabaseHas('medication_administrations', [
            'request_item_id' => $item->id,
            'witness_confirmed' => true,
        ]);
    }

    public function test_omitted_dose_does_not_count_toward_completion(): void
    {
        [$item, $nurse, $detail] = $this->seedInFacilityMarOrder(MedicationFrequency::ONCE, 1);
        $this->assertSame(1, $detail->total_administrations);

        app(MedicationAdministrationService::class)->administer($item->fresh(), [
            'status' => MedicationAdministrationStatus::OMITTED->value,
            'omission_reason' => 'Patient NPO',
        ], null, $nurse);

        $this->assertFalse($item->fresh()->isCompleted());

        app(MedicationAdministrationService::class)->administer($item->fresh(), [
            'status' => MedicationAdministrationStatus::GIVEN->value,
        ], null, $nurse);

        $this->assertTrue($item->fresh()->isCompleted());
    }

    /**
     * @return array{RequestItem, User, PrescriptionDetail, Medication}
     */
    protected function seedInFacilityMarOrder(
        MedicationFrequency $frequency,
        int $durationDays,
        bool $requiresPayment = false,
        EncounterType $encounterType = EncounterType::INPATIENT,
        bool $paid = true,
    ): array {
        $branch = Branch::factory()->default()->create();
        $patient = Patient::factory()->create();
        $nurse = User::factory()->create(['branch_id' => $branch->id]);
        Permission::findOrCreate('administer_medication', 'web');
        $nurse->givePermissionTo('administer_medication');

        $encounter = Encounter::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'type' => $encounterType,
            'status' => EncounterStatus::IN_PROGRESS,
        ]);

        $category = ServiceCategory::factory()->create(['code' => 'MED']);
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'requires_payment_before' => $requiresPayment,
        ]);
        $medication = Medication::factory()->create(['service_id' => $service->id]);

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

        $detail = PrescriptionDetail::create([
            'request_item_id' => $item->id,
            'frequency' => $frequency->value,
            'duration_days' => $durationDays,
            'route' => 'po',
            'dose_amount' => 1,
            'administration_context' => AdministrationContext::IN_FACILITY,
            'course_started_at' => now(),
            'course_end_at' => now()->addDays($durationDays),
            'total_administrations' => app(\Modules\Pharmacy\Classes\Services\PrescriptionScheduleCalculator::class)
                ->compute([
                    'frequency' => $frequency->value,
                    'duration_days' => $durationDays,
                    'prn' => false,
                    'course_started_at' => now(),
                ])['total_administrations'],
        ]);

        if ($requiresPayment && class_exists(InvoiceLine::class)) {
            $invoice = Invoice::create([
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'encounter_id' => $encounter->id,
                'invoice_number' => 'INV-TEST-'.uniqid(),
                'status' => InvoiceStatus::Issued,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
                'subtotal' => 10,
                'total' => 10,
                'amount_paid' => $paid ? 10 : 0,
            ]);

            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'billable_type' => RequestItem::class,
                'billable_id' => $item->id,
                'service_id' => $service->id,
                'quantity' => 1,
                'unit_price' => 10,
                'line_total' => 10,
                'patient_responsibility_amount' => 10,
                'line_status' => $paid ? InvoiceLineStatus::Paid : InvoiceLineStatus::Unpaid,
            ]);
        }

        return [$item, $nurse, $detail, $medication];
    }

    /**
     * @return array{RequestItem, User, Medication, User}
     */
    protected function seedTakeHomeOrder(): array
    {
        $branch = Branch::factory()->default()->create();
        $patient = Patient::factory()->create();
        $pharmacist = User::factory()->create(['branch_id' => $branch->id]);
        Role::findOrCreate('pharmacist', 'web');
        $pharmacist->assignRole('pharmacist');

        $category = ServiceCategory::factory()->create(['code' => 'MED']);
        $service = Service::factory()->create(['category_id' => $category->id]);
        $medication = Medication::factory()->create(['service_id' => $service->id]);

        $request = ServiceRequest::factory()->create([
            'patient_id' => $patient->id,
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
            'duration_days' => 7,
            'route' => 'po',
            'administration_context' => AdministrationContext::TAKE_HOME,
            'course_started_at' => now(),
            'course_end_at' => now()->addDays(7),
            'total_administrations' => 14,
        ]);

        return [$item, User::factory()->create(), $medication, $pharmacist];
    }
}
