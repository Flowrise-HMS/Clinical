<?php

namespace Modules\Clinical\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Notifications\PatientAdmittedNotification;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PatientAdmissionNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Patient', 'Clinical'] as $module) {
            $this->artisan('module:migrate', ['module' => $module, '--force' => true]);
        }
    }

    public function test_admission_notifies_once_even_after_subsequent_transitions(): void
    {
        Notification::fake();

        $branch = BranchFactory::new()->create();

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $branch->id,
                'phone' => '+233511111111',
                'email' => 'admitted@example.com',
            ])
        );

        $encounter = EncounterFactory::new()
            ->forPatient($patient)
            ->state([
                'status' => EncounterStatus::PLANNED,
                'type' => EncounterType::OUTPATIENT,
            ])
            ->create();

        $service = app(EncounterService::class);
        $service->admitPatient($encounter->fresh());

        Notification::assertSentTimes(PatientAdmittedNotification::class, 1);

        $service->triage($encounter->fresh(), EncounterPriority::ROUTINE);

        Notification::assertSentTimes(PatientAdmittedNotification::class, 1);
    }
}
