<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Filament\Widgets\PendingFulfillmentsWidget;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy']);

    $this->branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );

    $service = Service::factory()->create([
        'category_id' => $this->medicationServiceCategory()->id,
        'requires_payment_before' => false,
    ]);

    $request = ServiceRequest::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
    ]);

    $this->item = RequestItem::factory()->create([
        'service_request_id' => $request->id,
        'service_id' => $service->id,
        'status' => 'pending',
    ]);

    PrescriptionDetail::create([
        'request_item_id' => $this->item->id,
        'frequency' => MedicationFrequency::BID->value,
        'duration_days' => 7,
        'route' => 'po',
        'administration_context' => AdministrationContext::TAKE_HOME,
        'course_started_at' => now(),
        'course_end_at' => now()->addDays(7),
        'total_administrations' => 14,
    ]);

    Role::findOrCreate('pharmacist', 'web');
    Role::findOrCreate('nurse', 'web');
    Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate('administer_medication', 'web');
    Permission::findOrCreate('dispense_medication', 'web');
});

it('denies dispense capability for nurses', function (): void {
    $nurse = User::factory()->create(['branch_id' => $this->branch->id]);
    $nurse->assignRole('nurse');
    $nurse->givePermissionTo('administer_medication');

    expect(app(MedicationFulfillmentPolicy::class)->canDispense($this->item, $nurse))->toBeFalse();
});

it('allows dispense capability for pharmacists', function (): void {
    $pharmacist = User::factory()->create(['branch_id' => $this->branch->id]);
    $pharmacist->assignRole('pharmacist');
    $pharmacist->givePermissionTo('dispense_medication');

    expect(app(MedicationFulfillmentPolicy::class)->canDispense($this->item, $pharmacist))->toBeTrue();
});

it('allows dispense capability for any user granted the dispense permission', function (): void {
    $staff = User::factory()->create(['branch_id' => $this->branch->id]);
    $staff->givePermissionTo('dispense_medication');

    expect(app(MedicationFulfillmentPolicy::class)->canDispense($this->item, $staff))->toBeTrue();
});

it('allows dispense capability for super admins', function (): void {
    $superAdmin = User::factory()->create(['branch_id' => $this->branch->id]);
    $superAdmin->assignRole('super_admin');
    $superAdmin->givePermissionTo('dispense_medication');

    expect(app(MedicationFulfillmentPolicy::class)->canDispense($this->item, $superAdmin))->toBeTrue();
});

it('hides the dispense table action from nurses on pending fulfillments', function (): void {
    $nurse = User::factory()->create(['branch_id' => $this->branch->id]);
    $nurse->assignRole('nurse');
    $nurse->givePermissionTo('administer_medication');

    Livewire::actingAs($nurse)
        ->test(PendingFulfillmentsWidget::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertCanSeeTableRecords([$this->item])
        ->assertTableActionHidden('dispense', $this->item);
});

it('shows the dispense table action to pharmacists on pending fulfillments', function (): void {
    $pharmacist = User::factory()->create(['branch_id' => $this->branch->id]);
    $pharmacist->assignRole('pharmacist');
    $pharmacist->givePermissionTo('dispense_medication');

    Livewire::actingAs($pharmacist)
        ->test(PendingFulfillmentsWidget::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertCanSeeTableRecords([$this->item])
        ->assertTableActionVisible('dispense', $this->item);
});
