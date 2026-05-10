<?php

namespace Modules\Clinical\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Notifications\Concerns\BuildsPatientFacingChannels;

class PatientDischargedNotification extends Notification
{
    use BuildsPatientFacingChannels;

    public function __construct(protected Encounter $encounter) {}

    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $encounter = $this->encounter->loadMissing(['patient', 'branch']);

        $disposition = $encounter->discharge_disposition?->getLabel();

        return (new MailMessage)
            ->subject(__('Discharge summary — :branch', ['branch' => $encounter->branch?->name ?? config('app.name')]))
            ->line(__('Hello :name,', ['name' => $encounter->patient?->full_name ?? __('Patient')]))
            ->line(__('Your visit (encounter :number) has ended.', ['number' => $encounter->encounter_number]))
            ->when($encounter->discharged_at, fn (MailMessage $m) => $m->line(__('Discharged at: :time', ['time' => $encounter->discharged_at->toDayDateTimeString()])))
            ->when($disposition, fn (MailMessage $m) => $m->line(__('Disposition: :value', ['value' => $disposition])))
            ->line(__('Follow any instructions given by your care team. Contact :branch for follow-up questions.', [
                'branch' => $encounter->branch?->name ?? config('app.name'),
            ]))
            ->salutation(config('app.name'));
    }

    public function toSms(object $notifiable): string
    {
        $encounter = $this->encounter->loadMissing(['branch']);

        return __(
            'Discharged from :branch. Encounter :number closed. Follow your care team\'s instructions.',
            [
                'branch' => $encounter->branch?->name ?? config('app.name'),
                'number' => $encounter->encounter_number,
            ]
        );
    }
}
