<?php

namespace Modules\Clinical\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Notifications\Concerns\BuildsPatientFacingChannels;

class PatientAdmittedNotification extends Notification
{
    use BuildsPatientFacingChannels;

    public function __construct(protected Encounter $encounter) {}

    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable);
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
