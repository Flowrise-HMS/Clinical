<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Clinical\Classes\Services\MedicationAdministrationService;
use Modules\Clinical\Classes\Services\MedicationDoseScheduleService;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Filament\Clusters\Clinical\Pages\MedicationAdministrationBoard;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\MedicationCanvas;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Classes\Services\PrescriptionScheduleCalculator;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Billing', 'Clinical', 'Pharmacy']);

    config([
        'clinical.mar_payment.require_before_mar' => false,
        'clinical.mar_reminders.lead_minutes' => 15,
        'clinical.mar_reminders.grace_minutes' => 30,
    ]);

    Carbon::setTestNow('2026-09-17 07:30:00');

    $this->branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
    $this->nurse = User::factory()->create(['branch_id' => $this->branch->id]);

    foreach ([
        'View MedicationCanvas',
        'View Timeline',
        'ViewAny Patient',
        'View Patient',
        'View MedicationAdministrationBoard',
        'administer_medication',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->nurse->givePermissionTo($permission);
    }
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function seedScheduledOrder(
    TestCase $test,
    Encounter $encounter,
    MedicationFrequency $frequency,
    int $durationDays,
    ?Carbon $startedAt = null,
    ?string $name = null,
): array {
    $service = Service::factory()->create([
        'branch_id' => $encounter->branch_id,
        'category_id' => (fn () => $this->medicationServiceCategory())->call($test)->id,
        'name' => $name ?? fake()->unique()->word().' 500mg',
        'requires_payment_before' => false,
    ]);
    Medication::factory()->create(['service_id' => $service->id]);

    $request = ServiceRequest::factory()->create([
        'patient_id' => $encounter->patient_id,
        'encounter_id' => $encounter->id,
        'branch_id' => $encounter->branch_id,
    ]);

    $item = RequestItem::factory()->create([
        'service_request_id' => $request->id,
        'service_id' => $service->id,
        'status' => 'pending',
    ]);

    $start = $startedAt ?? now();
    $calc = app(PrescriptionScheduleCalculator::class)->compute([
        'frequency' => $frequency->value,
        'duration_days' => $durationDays,
        'prn' => false,
        'course_started_at' => $start,
    ]);

    $detail = PrescriptionDetail::create([
        'request_item_id' => $item->id,
        'frequency' => $frequency->value,
        'duration_days' => $durationDays,
        'route' => 'po',
        'dose_amount' => 1,
        'administration_context' => AdministrationContext::IN_FACILITY,
        'course_started_at' => $start,
        'course_end_at' => $calc['course_end_at'],
        'next_dose_at' => $calc['dose_times'][0] ?? $start,
        'total_administrations' => $calc['total_administrations'],
        'schedule_type' => 'interval_based',
    ]);

    return [$item, $detail];
}

it('allows customizing start time and interval schedule for in-facility medication', function (): void {
    $encounter = Encounter::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'type' => EncounterType::INPATIENT,
        'status' => EncounterStatus::IN_PROGRESS,
    ]);

    [$item, $detail] = seedScheduledOrder($this, $encounter, MedicationFrequency::BID, 2);

    $service = app(MedicationDoseScheduleService::class);
    $initialSlots = $service->getSchedule($detail);
    expect($initialSlots)->toHaveCount(4);
    expect($initialSlots[0]->dueAt->format('H:i'))->toBe('07:30');
    expect($initialSlots[1]->dueAt->format('H:i'))->toBe('19:30');

    // Customize schedule to start at 10:00:00
    $newStart = '2026-09-17 10:00:00';
    $service->customizeSchedule($item, [
        'course_started_at' => $newStart,
        'schedule_type' => 'interval_based',
    ], $this->nurse);

    $item->refresh();
    $detail->refresh();

    expect($detail->course_started_at->format('Y-m-d H:i:s'))->toBe($newStart);
    expect($detail->schedule_type)->toBe('interval_based');
    expect($detail->next_dose_at->format('Y-m-d H:i:s'))->toBe($newStart);

    $updatedSlots = $service->getSchedule($detail);
    expect($updatedSlots[0]->dueAt->format('H:i'))->toBe('10:00');
    expect($updatedSlots[1]->dueAt->format('H:i'))->toBe('22:00');
    expect($updatedSlots[2]->dueAt->format('H:i'))->toBe('10:00');
    expect($updatedSlots[3]->dueAt->format('H:i'))->toBe('22:00');
});

it('preserves historical given doses when rescheduling subsequent doses', function (): void {
    $encounter = Encounter::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'type' => EncounterType::INPATIENT,
        'status' => EncounterStatus::IN_PROGRESS,
    ]);

    [$item, $detail] = seedScheduledOrder($this, $encounter, MedicationFrequency::TID, 2);

    // Record the first dose at 07:30
    app(MedicationAdministrationService::class)->administer($item, [
        'status' => MedicationAdministrationStatus::GIVEN->value,
        'started_at' => now(),
        'dose_quantity' => 1,
    ], 'First dose given on time', $this->nurse);

    expect(MedicationAdministration::count())->toBe(1);
    $admin = MedicationAdministration::first();
    expect($admin->status)->toBe(MedicationAdministrationStatus::GIVEN);
    expect($admin->dose_slot_sequence)->toBe(1);

    // Doctor/nurse reschedules starting from 14:00
    $newStart = '2026-09-17 14:00:00';
    app(MedicationDoseScheduleService::class)->customizeSchedule($item, [
        'course_started_at' => $newStart,
        'schedule_type' => 'interval_based',
    ], $this->nurse);

    $item->refresh();
    $detail->refresh();

    // Verify given dose history is preserved intact
    $admin->refresh();
    expect($admin->status)->toBe(MedicationAdministrationStatus::GIVEN);
    expect($admin->dose_slot_sequence)->toBe(1);

    // Next due slot should be sequence 2
    $nextSlot = app(MedicationDoseScheduleService::class)->getNextDueSlot($item);
    expect($nextSlot)->not->toBeNull();
    expect($nextSlot->sequence)->toBe(2);
    // Interval for TID is 8h; slot 2 is at 22:00
    expect($nextSlot->dueAt->format('H:i'))->toBe('22:00');
});

it('supports custom daily dose times schedule', function (): void {
    $encounter = Encounter::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'type' => EncounterType::INPATIENT,
        'status' => EncounterStatus::IN_PROGRESS,
    ]);

    [$item, $detail] = seedScheduledOrder($this, $encounter, MedicationFrequency::BID, 2);

    app(MedicationDoseScheduleService::class)->customizeSchedule($item, [
        'course_started_at' => '2026-09-17 06:00:00',
        'schedule_type' => 'custom_times',
        'custom_dose_times' => [
            ['time' => '09:00'],
            ['time' => '21:00'],
        ],
    ], $this->nurse);

    $detail->refresh();
    expect($detail->schedule_type)->toBe('custom_times');
    expect($detail->custom_dose_times)->toBe([
        ['time' => '09:00'],
        ['time' => '21:00'],
    ]);

    $slots = app(MedicationDoseScheduleService::class)->getSchedule($detail);
    expect($slots)->toHaveCount(4);
    expect($slots[0]->dueAt->format('H:i'))->toBe('09:00');
    expect($slots[1]->dueAt->format('H:i'))->toBe('21:00');
    expect($slots[2]->dueAt->format('H:i'))->toBe('09:00');
    expect($slots[3]->dueAt->format('H:i'))->toBe('21:00');
});

it('allows customizing schedule via MedicationCanvas livewire action', function (): void {
    $encounter = Encounter::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'type' => EncounterType::INPATIENT,
        'status' => EncounterStatus::IN_PROGRESS,
    ]);

    [$item, $detail] = seedScheduledOrder($this, $encounter, MedicationFrequency::BID, 3);

    Livewire::actingAs($this->nurse)
        ->test(MedicationCanvas::class, ['patientId' => $this->patient->id])
        ->assertActionExists('scheduleMedication')
        ->callAction('scheduleMedication', data: [
            'course_started_at' => '2026-09-17 11:30:00',
            'schedule_type' => 'interval_based',
        ], arguments: ['requestItemId' => $item->id])
        ->assertHasNoActionErrors();

    $detail->refresh();
    expect($detail->course_started_at->format('H:i'))->toBe('11:30');
    expect($detail->next_dose_at->format('H:i'))->toBe('11:30');
});

it('renders MAR Board with schedule action and executes it', function (): void {
    $encounter = Encounter::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'type' => EncounterType::INPATIENT,
        'status' => EncounterStatus::IN_PROGRESS,
    ]);

    [$item, $detail] = seedScheduledOrder($this, $encounter, MedicationFrequency::BID, 2);

    Livewire::actingAs($this->nurse)
        ->test(MedicationAdministrationBoard::class)
        ->assertOk()
        ->assertTableActionExists('customize_schedule')
        ->assertTableActionExists('record_dose')
        ->callTableAction('customize_schedule', $item, data: [
            'course_started_at' => '2026-09-17 12:00:00',
            'schedule_type' => 'interval_based',
        ])
        ->assertHasNoTableActionErrors();

    $detail->refresh();
    expect($detail->course_started_at->format('H:i'))->toBe('12:00');
});
