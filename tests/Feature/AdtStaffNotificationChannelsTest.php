<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Models\AdmissionRequest;
use Modules\Clinical\Notifications\AdmissionRequestedNotification;
use Modules\Clinical\Settings\ClinicalSettings;
use Tests\TestCase;

/**
 * Staff-facing ADT alerts take their channels from the Clinical settings
 * (falling back to config) and only keep the ones the user can receive.
 */
class AdtStaffNotificationChannelsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical']);
    }

    public function test_channels_follow_the_adt_setting(): void
    {
        $nurse = User::factory()->create(['email' => 'nurse@example.com']);
        $notification = new AdmissionRequestedNotification(new AdmissionRequest);

        ClinicalSettings::fake(['adt_notifications_channels' => ['database', 'mail']]);
        $this->assertSame(['database', 'mail'], $notification->via($nurse));

        ClinicalSettings::fake(['adt_notifications_channels' => ['database']]);
        $this->assertSame(['database'], $notification->via($nurse));

        // SMS is configured but users carry no phone number: it is dropped, not sent blind.
        ClinicalSettings::fake(['adt_notifications_channels' => ['mail', 'sms']]);
        $this->assertSame(['mail'], $notification->via($nurse));
    }

    public function test_config_is_the_fallback_when_the_setting_is_unavailable(): void
    {
        $nurse = User::factory()->create(['email' => 'nurse@example.com']);
        config(['clinical.adt_notifications.channels' => ['database']]);

        // An unknown property falls back to config inside clinicalValue().
        ClinicalSettings::fake([]);
        $this->assertSame(['database', 'mail'], (new AdmissionRequestedNotification(new AdmissionRequest))->via($nurse));
    }

    public function test_setting_defaults_to_in_app_and_mail(): void
    {
        $this->assertSame(['database', 'mail'], (new \ReflectionProperty(ClinicalSettings::class, 'adt_notifications_channels'))->getDefaultValue());
        $this->assertSame(['database', 'mail'], app(ClinicalSettings::class)->adt_notifications_channels);
    }
}
