<?php

namespace Modules\Clinical\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Notifications\Concerns\PatientFacingAdtNotification;

/**
 * Sent when an outpatient (non-admission) visit is completed.
 */
class VisitCompletedNotification extends Notification
{
    use PatientFacingAdtNotification;

    public function __construct(protected Encounter $encounter) {}

    public function via(object $notifiable): array
    {
        return $this->settingsChannels($notifiable, 'visit_completed_mail', 'visit_completed_sms');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $encounter = $this->encounter->loadMissing(['patient', 'branch']);
        $followUp = $this->followUpAt($encounter);

        return (new MailMessage)
            ->subject(__('Your visit at :branch is complete', ['branch' => $this->branchName($encounter)]))
            ->line(__('Hello :name,', ['name' => $this->patientName($encounter)]))
            ->line(__('Your visit :number at :branch is complete.', [
                'number' => $encounter->encounter_number,
                'branch' => $this->branchName($encounter),
            ]))
            ->when($followUp, fn (MailMessage $m) => $m->line(__('Follow-up appointment: :time', ['time' => $followUp->toDayDateTimeString()])))
            ->line(__('Follow any instructions given by your care team. Contact :branch with any questions.', ['branch' => $this->branchName($encounter)]))
            ->salutation(config('app.name'));
    }

    public function toSms(object $notifiable): string
    {
        $encounter = $this->encounter->loadMissing(['branch']);

        return __('Your visit :number at :branch is complete. Follow your care team\'s instructions.', [
            'number' => $encounter->encounter_number,
            'branch' => $this->branchName($encounter),
        ]);
    }

    protected function followUpAt(Encounter $encounter): ?Carbon
    {
        $value = $encounter->metadata['follow_up']['at'] ?? null;

        return filled($value) ? Carbon::parse($value) : null;
    }
}
