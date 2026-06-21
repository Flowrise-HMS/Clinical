<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Modules\Clinical\Classes\Services\MedicationDoseScheduleService;
use Modules\Pharmacy\Classes\Services\PrescriptionScheduleCalculator;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\MedicationDoseReminderLog;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Clinical\Notifications\MedicationDueDoseNotification;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceCategory;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MedicationDoseReminderTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy']);

        config(['clinical.mar_reminders.enabled' => true]);
        config(['clinical.mar_payment.require_before_mar' => false]);
    }

    public function test_q8h_two_day_schedule_builds_six_slots(): void
    {
        $detail = $this->makePrescriptionDetail(MedicationFrequency::Q8H, 2);
        $slots = app(PrescriptionScheduleCalculator::class)->buildDoseSchedule($detail);

        $this->assertCount(6, $slots);
        $this->assertSame(1, $slots[0]->sequence);
        $this->assertSame(6, $slots[5]->sequence);
    }

    public function test_next_dose_at_advances_after_administration(): void
    {
        [$item, $nurse, $detail] = $this->seedInFacilityOrder(MedicationFrequency::Q8H, 2);

        $scheduleService = app(MedicationDoseScheduleService::class);
        $first = $scheduleService->getNextDueSlot($item);
        $this->assertNotNull($first);

        app(\Modules\Clinical\Classes\Services\MedicationAdministrationService::class)->administer($item, [
            'status' => 'given',
            'started_at' => $first->dueAt,
        ], null, $nurse);

        $detail->refresh();
        $second = $scheduleService->getNextDueSlot($item->fresh());
        $this->assertNotNull($second);
        $this->assertTrue($second->sequence > $first->sequence);
    }

    public function test_reminder_command_dedupes_notifications(): void
    {
        Notification::fake();

        [$item, $nurse, $detail] = $this->seedInFacilityOrder(MedicationFrequency::STAT, 1);
        $pastDue = now()->subHour();
        $detail->update([
            'course_started_at' => $pastDue,
            'next_dose_at' => $pastDue,
        ]);

        $this->artisan('clinical:mar-dose-reminders')->assertSuccessful();
        Notification::assertSentTo($nurse, MedicationDueDoseNotification::class);

        $this->artisan('clinical:mar-dose-reminders')->assertSuccessful();
        Notification::assertSentToTimes($nurse, MedicationDueDoseNotification::class, 1);

        $this->assertSame(1, MedicationDoseReminderLog::query()->count());
    }

    protected function makePrescriptionDetail(MedicationFrequency $frequency, int $days): PrescriptionDetail
    {
        $schedule = app(PrescriptionScheduleCalculator::class)->compute([
            'frequency' => $frequency->value,
            'duration_days' => $days,
            'prn' => false,
            'course_started_at' => now()->setTime(8, 0),
        ]);

        return new PrescriptionDetail([
            'frequency' => $frequency->value,
            'duration_days' => $days,
            'prn' => false,
            'course_started_at' => $schedule['course_started_at'],
            'course_end_at' => $schedule['course_end_at'],
            'total_administrations' => $schedule['total_administrations'],
            'administration_context' => AdministrationContext::IN_FACILITY,
        ]);
    }

    /**
     * @return array{RequestItem, User, PrescriptionDetail}
     */
    protected function seedInFacilityOrder(MedicationFrequency $frequency, int $days): array
    {
        $branch = Branch::factory()->default()->create();
        $patient = Patient::factory()->create();
        $nurse = User::factory()->create(['branch_id' => $branch->id]);
        Permission::findOrCreate('administer_medication', 'web');
        $nurse->givePermissionTo('administer_medication');

        $encounter = Encounter::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'type' => EncounterType::INPATIENT,
            'status' => EncounterStatus::IN_PROGRESS,
        ]);

        $category = $this->medicationServiceCategory();
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'requires_payment_before' => false,
        ]);

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

        $schedule = app(PrescriptionScheduleCalculator::class)->compute([
            'frequency' => $frequency->value,
            'duration_days' => $days,
            'prn' => false,
            'course_started_at' => now()->setTime(8, 0),
        ]);

        $detail = PrescriptionDetail::create([
            'request_item_id' => $item->id,
            'frequency' => $frequency->value,
            'duration_days' => $days,
            'route' => 'po',
            'administration_context' => AdministrationContext::IN_FACILITY,
            'course_started_at' => $schedule['course_started_at'],
            'course_end_at' => $schedule['course_end_at'],
            'total_administrations' => $schedule['total_administrations'],
            'next_dose_at' => $schedule['course_started_at'],
        ]);

        return [$item, $nurse, $detail];
    }
}
