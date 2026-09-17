<?php

namespace Modules\Clinical\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterLocationEvent;
use Modules\Clinical\Notifications\Concerns\PatientFacingAdtNotification;

/**
 * Sent when an inpatient is moved to another ward or bed within the facility.
 */
class PatientTransferredNotification extends Notification
{
    use PatientFacingAdtNotification;

    public function __construct(protected Encounter $encounter, protected ?EncounterLocationEvent $event = null) {}

    public function via(object $notifiable): array
    {
        return $this->settingsChannels($notifiable, 'patient_transferred_mail', 'patient_transferred_sms');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $encounter = $this->encounter->loadMissing(['patient', 'branch', 'location', 'bed']);
        $ward = $this->wardLabel($encounter) ?? __('another ward');

        return (new MailMessage)
            ->subject(__('Moved to :ward — :branch', ['ward' => $encounter->location?->name ?? __('another ward'), 'branch' => $this->branchName($encounter)]))
            ->line(__('Hello :name,', ['name' => $this->patientName($encounter)]))
            ->line(__(':patient has been moved to :ward at :branch (encounter :number).', [
                'patient' => $this->patientName($encounter),
                'ward' => $ward,
                'branch' => $this->branchName($encounter),
                'number' => $encounter->encounter_number,
            ]))
            ->salutation(config('app.name'));
    }

    public function toSms(object $notifiable): string
    {
        $encounter = $this->encounter->loadMissing(['branch', 'location', 'bed']);

        return __('Moved to :ward at :branch. Encounter :number.', [
            'ward' => $this->wardLabel($encounter) ?? __('another ward'),
            'branch' => $this->branchName($encounter),
            'number' => $encounter->encounter_number,
        ]);
    }
}
