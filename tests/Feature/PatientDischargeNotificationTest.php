<?php

namespace Modules\Clinical\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Notifications\PatientDischargedNotification;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PatientDischargeNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Patient', 'Clinical'] as $module) {
            $this->artisan('module:migrate', ['module' => $module, '--force' => true]);
        }
    }

    public function test_discharge_sends_notification_to_patient(): void
    {
        Notification::fake();

        $branch = BranchFactory::new()->create();

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $branch->id,
                'phone' => '+233522222222',
                'email' => 'discharged@example.com',
            ])
        );

        $encounter = EncounterFactory::new()
            ->forPatient($patient)
            ->active()
            ->create();

        app(EncounterService::class)->discharge($encounter->fresh());

        Notification::assertSentTo($patient, PatientDischargedNotification::class);
    }
}
