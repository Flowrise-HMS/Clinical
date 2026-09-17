<?php

namespace Modules\Clinical\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Notifications\Concerns\PatientFacingAdtNotification;

/**
 * Sent when an outpatient (non-admission) visit starts.
 */
class VisitStartedNotification extends Notification
{
    use PatientFacingAdtNotification;

    public function __construct(protected Encounter $encounter) {}

    public function via(object $notifiable): array
    {
        return $this->settingsChannels($notifiable, 'visit_started_mail', 'visit_started_sms');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $encounter = $this->encounter->loadMissing(['patient', 'branch', 'department']);

        return (new MailMessage)
            ->subject(__('Your visit at :branch', ['branch' => $this->branchName($encounter)]))
            ->line(__('Hello :name,', ['name' => $this->patientName($encounter)]))
            ->line(__('Your visit :number has started at :branch.', [
                'number' => $encounter->encounter_number,
                'branch' => $this->branchName($encounter),
            ]))
            ->when($encounter->department, fn (MailMessage $m) => $m->line(__('Department: :dept', ['dept' => $encounter->department->name])))
            ->line(__('Bring your hospital card on each visit.'))
            ->salutation(config('app.name'));
    }

    public function toSms(object $notifiable): string
    {
        $encounter = $this->encounter->loadMissing(['branch']);

        return __('Your visit :number has started at :branch. Bring your hospital card on each visit.', [
            'number' => $encounter->encounter_number,
            'branch' => $this->branchName($encounter),
        ]);
    }
}
