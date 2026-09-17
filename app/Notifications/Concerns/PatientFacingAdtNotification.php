<?php

namespace Modules\Clinical\Notifications\Concerns;

use Modules\Clinical\Enums\ParticipantRole;
use Modules\Clinical\Enums\ParticipantStatus;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Notifications\Concerns\RespectsNotificationSettings;
use Modules\Core\Support\AppSettings;

/**
 * Shared plumbing for the patient-facing admission/transfer/discharge/visit
 * messages: channel resolution against the notification settings and a few
 * encounter presenters.
 */
trait PatientFacingAdtNotification
{
    use BuildsPatientFacingChannels, RespectsNotificationSettings;

    /**
     * @return array<int, string>
     */
    protected function settingsChannels(object $notifiable, string $mailKey, string $smsKey): array
    {
        $channels = $this->channelsFor($notifiable);

        try {
            $settings = app(AppSettings::class)->notifications();
            $billing = app(AppSettings::class)->billing();

            return $this->applyNotificationSettings(
                $channels,
                (bool) ($settings->{$mailKey} ?? true),
                (bool) ($settings->{$smsKey} ?? true),
                (bool) $billing->sms_enabled,
            );
        } catch (\Throwable) {
            return $channels;
        }
    }

    protected function branchName(Encounter $encounter): string
    {
        return $encounter->branch?->name ?? config('app.name');
    }

    protected function patientName(Encounter $encounter): string
    {
        return $encounter->patient?->full_name ?? __('Patient');
    }

    protected function wardLabel(Encounter $encounter): ?string
    {
        $encounter->loadMissing(['location', 'bed']);

        $parts = array_filter([$encounter->location?->name, $encounter->bed?->name ? __('bed :bed', ['bed' => $encounter->bed->name]) : null]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    protected function attendingName(Encounter $encounter): ?string
    {
        return $encounter->participants()
            ->where('status', ParticipantStatus::ACTIVE)
            ->whereIn('role', [ParticipantRole::ATTENDING, ParticipantRole::PRIMARY_PROVIDER])
            ->with('user')
            ->first()?->user?->name;
    }
}
