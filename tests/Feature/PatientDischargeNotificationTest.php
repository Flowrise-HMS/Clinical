<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Enums\AdtDestinationType;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Events\EncounterFinished;
use Modules\Clinical\Notifications\PatientDischargedNotification;
use Modules\Clinical\Notifications\VisitCompletedNotification;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PatientDischargeNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected Patient $patient;

    protected EmergencyContact $contact;

    protected Location $bed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical']);
        config(['clinical.discharge.enforce_readiness' => false]);

        $this->branch = Branch::factory()->default()->create();
        $this->actingAs(User::factory()->create(['branch_id' => $this->branch->id]));

        $this->patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $this->branch->id,
                'phone' => '+233522222222',
                'email' => 'discharged@example.com',
            ])
        );
        $this->contact = EmergencyContact::factory()->create([
            'patient_id' => $this->patient->id,
            'email' => 'kin@example.com',
            'phone' => '+233533333333',
            'can_receive_sms' => true,
        ]);

        $ward = Location::factory()->room()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->bed = Location::factory()->bed()->create(['branch_id' => $this->branch->id, 'parent_id' => $ward->id, 'is_active' => true]);
    }

    public function test_inpatient_discharge_notifies_patient_and_contacts_and_still_finishes_the_encounter(): void
    {
        Notification::fake();
        Event::fake([EncounterFinished::class]);

        $encounter = app(AdtService::class)->admit($this->patient, $this->bed->id);
        app(AdtService::class)->discharge($encounter);

        Notification::assertSentTo($this->patient, PatientDischargedNotification::class, function (PatientDischargedNotification $notification, array $channels) {
            return in_array('mail', $channels, true)
                && in_array('sms', $channels, true)
                && str_contains($notification->toSms($this->patient), 'discharged from');
        });
        Notification::assertSentTo($this->contact, PatientDischargedNotification::class);
        Notification::assertNotSentTo($this->patient, VisitCompletedNotification::class);
        Event::assertDispatched(EncounterFinished::class);
    }

    public function test_outpatient_completion_sends_a_visit_completed_message(): void
    {
        Notification::fake();
        Event::fake([EncounterFinished::class]);

        $encounter = EncounterFactory::new()->forPatient($this->patient)->state([
            'branch_id' => $this->branch->id,
            'status' => EncounterStatus::IN_PROGRESS,
            'type' => EncounterType::OUTPATIENT,
        ])->create();

        app(EncounterService::class)->completeEncounter($encounter->fresh());

        Notification::assertSentTo($this->patient, VisitCompletedNotification::class);
        Notification::assertSentTo($this->contact, VisitCompletedNotification::class);
        Notification::assertNotSentTo($this->patient, PatientDischargedNotification::class);
        Event::assertDispatched(EncounterFinished::class);
    }

    public function test_transfer_out_uses_transfer_wording(): void
    {
        Notification::fake();

        $encounter = app(AdtService::class)->admit($this->patient, $this->bed->id);
        app(AdtService::class)->transferOut($encounter, AdtDestinationType::ExternalFacility, 'Korle Bu Teaching Hospital');

        Notification::assertSentTo($this->patient, PatientDischargedNotification::class, function (PatientDischargedNotification $notification) {
            return str_contains($notification->toSms($this->patient), 'Korle Bu Teaching Hospital');
        });
    }

    public function test_deceased_disposition_never_messages_the_patient_and_mails_contacts_only(): void
    {
        Notification::fake();

        $encounter = app(AdtService::class)->admit($this->patient, $this->bed->id);
        app(AdtService::class)->discharge($encounter, DischargeDisposition::DECEASED);

        Notification::assertNotSentTo($this->patient, PatientDischargedNotification::class);
        $this->assertSame([], (new PatientDischargedNotification($encounter->fresh()))->via($this->patient));
        Notification::assertSentTo($this->contact, PatientDischargedNotification::class, function (PatientDischargedNotification $notification, array $channels) {
            $mail = $notification->toMail($this->contact);
            $rendered = implode("\n", array_map(fn ($line) => (string) $line, $mail->introLines));

            return $channels === ['mail']
                && str_contains($rendered, 'Please contact')
                && ! str_contains(strtolower($rendered), 'deceased');
        });
    }
}
