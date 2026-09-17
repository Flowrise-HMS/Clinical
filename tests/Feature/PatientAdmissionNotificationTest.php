<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Notifications\PatientAdmittedNotification;
use Modules\Clinical\Notifications\PatientTransferredNotification;
use Modules\Clinical\Notifications\VisitStartedNotification;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Core\Settings\NotificationSettings;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PatientAdmissionNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected User $doctor;

    protected Patient $patient;

    protected EmergencyContact $contact;

    protected Location $ward;

    protected Location $bedA;

    protected Location $bedB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical']);

        $this->branch = Branch::factory()->default()->create();
        $this->doctor = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->actingAs($this->doctor);

        $this->patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $this->branch->id,
                'phone' => '+233511111111',
                'email' => 'admitted@example.com',
            ])
        );
        $this->contact = EmergencyContact::factory()->create([
            'patient_id' => $this->patient->id,
            'email' => 'next-of-kin@example.com',
            'phone' => '+233522222222',
            'can_receive_sms' => true,
        ]);

        $this->ward = Location::factory()->room()->create(['branch_id' => $this->branch->id, 'name' => 'Female Medical', 'is_active' => true]);
        $this->bedA = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $this->ward->id, 'name' => 'Bed 4', 'is_active' => true]);
        $this->bedB = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $this->ward->id, 'name' => 'Bed 5', 'is_active' => true]);
    }

    public function test_outpatient_arrival_sends_a_visit_started_message_not_an_admission(): void
    {
        Notification::fake();

        $encounter = EncounterFactory::new()->forPatient($this->patient)->state([
            'branch_id' => $this->branch->id,
            'status' => EncounterStatus::PLANNED,
            'type' => EncounterType::OUTPATIENT,
        ])->create();

        $service = app(EncounterService::class);
        $service->admitPatient($encounter->fresh());
        $service->triage($encounter->fresh(), EncounterPriority::ROUTINE);

        Notification::assertSentTimes(VisitStartedNotification::class, 2);
        Notification::assertSentTo($this->patient, VisitStartedNotification::class);
        Notification::assertSentTo($this->contact, VisitStartedNotification::class);
        Notification::assertNotSentTo($this->patient, PatientAdmittedNotification::class);
    }

    public function test_direct_admission_notifies_patient_and_contacts_once_with_ward_and_bed(): void
    {
        Notification::fake();

        $encounter = app(AdtService::class)->admit($this->patient, $this->bedA->id);

        Notification::assertSentTimes(PatientAdmittedNotification::class, 2);
        Notification::assertNotSentTo($this->patient, VisitStartedNotification::class);
        Notification::assertSentTo($this->patient, PatientAdmittedNotification::class, function (PatientAdmittedNotification $notification, array $channels) {
            $mail = $notification->toMail($this->patient);
            $rendered = implode("\n", array_map(fn ($line) => (string) $line, $mail->introLines));

            return in_array('mail', $channels, true)
                && in_array('sms', $channels, true)
                && str_contains($rendered, 'Female Medical')
                && str_contains($rendered, 'bed Bed 4')
                && str_contains($notification->toSms($this->patient), 'Female Medical');
        });

        $this->assertNotNull($encounter->fresh()->metadata['notified_admission_at'] ?? null);

        app(AdtService::class)->transferInternal($encounter, $this->bedB->id);

        Notification::assertSentTimes(PatientAdmittedNotification::class, 2);
        Notification::assertSentTimes(PatientTransferredNotification::class, 2);
    }

    public function test_accepting_an_admission_request_notifies_even_when_the_visit_already_started(): void
    {
        Notification::fake();

        $encounter = EncounterFactory::new()->forPatient($this->patient)->state([
            'branch_id' => $this->branch->id,
            'status' => EncounterStatus::ARRIVED,
            'type' => EncounterType::OUTPATIENT,
        ])->create();

        $adt = app(AdtService::class);
        $request = $adt->requestAdmission($encounter, $this->ward->id, requestedBy: $this->doctor->id);

        Notification::assertNotSentTo($this->patient, PatientAdmittedNotification::class);

        $adt->acceptAdmission($request, $this->bedA->id, actedBy: $this->doctor->id);

        Notification::assertSentTo($this->patient, PatientAdmittedNotification::class);
        Notification::assertSentTo($this->contact, PatientAdmittedNotification::class);
    }

    public function test_emergency_contacts_can_be_excluded_by_setting(): void
    {
        Notification::fake();

        $settings = app(NotificationSettings::class);
        $settings->include_emergency_contacts = false;
        $settings->save();

        app(AdtService::class)->admit($this->patient, $this->bedA->id);

        Notification::assertSentTo($this->patient, PatientAdmittedNotification::class);
        Notification::assertNotSentTo($this->contact, PatientAdmittedNotification::class);
    }

    public function test_channels_follow_the_admission_toggles(): void
    {
        Notification::fake();

        $settings = app(NotificationSettings::class);
        $settings->patient_admitted_mail = false;
        $settings->patient_admitted_sms = false;
        $settings->save();

        app(AdtService::class)->admit($this->patient, $this->bedA->id);

        // With both channels off, via() returns nothing and the fake records no delivery.
        Notification::assertNotSentTo($this->patient, PatientAdmittedNotification::class);
        Notification::assertNotSentTo($this->contact, PatientAdmittedNotification::class);

        $this->assertSame([], (new PatientAdmittedNotification($this->patient->activeEncounter()->first()))->via($this->patient));
    }
}
