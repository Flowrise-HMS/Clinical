<?php

namespace Modules\Clinical\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Notifications\Concerns\BuildsPatientFacingChannels;
use Modules\Core\Notifications\Concerns\RespectsNotificationSettings;
use Modules\Core\Support\AppSettings;

class PatientAdmittedNotification extends Notification
{
    use BuildsPatientFacingChannels, RespectsNotificationSettings;

    public function __construct(protected Encounter $encounter) {}

    public function via(object $notifiable): array
    {
        $channels = $this->channelsFor($notifiable);

        try {
            $settings = app(AppSettings::class)->notifications();
            $billing = app(AppSettings::class)->billing();

            return $this->applyNotificationSettings(
                $channels,
                $settings->patient_admitted_mail,
                $settings->patient_admitted_sms,
                $billing->sms_enabled,
            );
        } catch (\Throwable) {
            return $channels;
        }
    }

    public function toMail(object $notifiable): MailMessage
    {
        $encounter = $this->encounter->loadMissing(['patient', 'branch', 'department']);

        return (new MailMessage)
            ->subject(__('You have been admitted — :branch', ['branch' => $encounter->branch?->name ?? config('app.name')]))
            ->line(__('Hello :name,', ['name' => $encounter->patient?->full_name ?? __('Patient')]))
            ->line(__('Your encounter :number has started at :branch.', [
                'number' => $encounter->encounter_number,
                'branch' => $encounter->branch?->name ?? config('app.name'),
            ]))
            ->when($encounter->admitted_at, fn (MailMessage $m) => $m->line(__('Admitted at: :time', ['time' => $encounter->admitted_at->toDayDateTimeString()])))
            ->when($encounter->department, fn (MailMessage $m) => $m->line(__('Department: :dept', ['dept' => $encounter->department->name])))
            ->line(__('Bring your hospital card on each visit.'))
            ->salutation(config('app.name'));
    }

    public function toSms(object $notifiable): string
    {
        $encounter = $this->encounter->loadMissing(['patient', 'branch']);

        return __(
            'Admitted at :branch. Encounter :number. Bring your hospital card on each visit.',
            [
                'branch' => $encounter->branch?->name ?? config('app.name'),
                'number' => $encounter->encounter_number,
            ]
        );
    }
}
