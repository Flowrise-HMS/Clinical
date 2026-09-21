<?php

namespace Modules\Clinical\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Notifications\Concerns\PatientFacingAdtNotification;

/**
 * Sent to the patient and their emergency contacts when the patient is
 * admitted to a ward bed (direct admission, accepted request, or transfer in).
 */
class PatientAdmittedNotification extends Notification implements ShouldQueue
{
    use PatientFacingAdtNotification, Queueable;

    public function __construct(protected Encounter $encounter) {}

    public function via(object $notifiable): array
    {
        return $this->settingsChannels($notifiable, 'patient_admitted_mail', 'patient_admitted_sms');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $encounter = $this->encounter->loadMissing(['patient', 'branch', 'department', 'location', 'bed']);
        $ward = $this->wardLabel($encounter);
        $attending = $this->attendingName($encounter);

        return (new MailMessage)
            ->subject(__('Admitted to :branch', ['branch' => $this->branchName($encounter)]))
            ->line(__('Hello :name,', ['name' => $this->patientName($encounter)]))
            ->line(__(':patient has been admitted to :branch (encounter :number).', [
                'patient' => $this->patientName($encounter),
                'branch' => $this->branchName($encounter),
                'number' => $encounter->encounter_number,
            ]))
            ->when($ward, fn (MailMessage $m) => $m->line(__('Ward: :ward', ['ward' => $ward])))
            ->when($encounter->department, fn (MailMessage $m) => $m->line(__('Department: :dept', ['dept' => $encounter->department->name])))
            ->when($attending, fn (MailMessage $m) => $m->line(__('Attending clinician: :name', ['name' => $attending])))
            ->when($encounter->admitted_at, fn (MailMessage $m) => $m->line(__('Admitted at: :time', ['time' => $encounter->admitted_at->toDayDateTimeString()])))
            ->line(__('Visiting hours and what to bring can be confirmed with the ward.'))
            ->salutation(config('app.name'));
    }

    public function toSms(object $notifiable): string
    {
        $encounter = $this->encounter->loadMissing(['branch', 'location', 'bed']);
        $ward = $this->wardLabel($encounter);

        return __('Admitted to :branch:ward. Encounter :number.', [
            'branch' => $this->branchName($encounter),
            'ward' => $ward ? ' ('.$ward.')' : '',
            'number' => $encounter->encounter_number,
        ]);
    }
}
