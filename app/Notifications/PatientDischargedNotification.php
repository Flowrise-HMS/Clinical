<?php

namespace Modules\Clinical\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Notifications\Concerns\PatientFacingAdtNotification;
use Modules\Patient\Models\Patient;

/**
 * Sent when an inpatient stay ends (discharge or transfer out). A deceased
 * disposition never messages the patient and reaches emergency contacts by
 * mail only, with wording that asks them to contact the facility.
 */
class PatientDischargedNotification extends Notification
{
    use PatientFacingAdtNotification;

    public function __construct(protected Encounter $encounter) {}

    public function via(object $notifiable): array
    {
        if ($this->isDeceased()) {
            if ($notifiable instanceof Patient) {
                return [];
            }

            return array_values(array_filter(
                $this->settingsChannels($notifiable, 'patient_discharged_mail', 'patient_discharged_sms'),
                fn (string $channel): bool => $channel === 'mail',
            ));
        }

        return $this->settingsChannels($notifiable, 'patient_discharged_mail', 'patient_discharged_sms');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $encounter = $this->encounter->loadMissing(['patient', 'branch']);
        $branch = $this->branchName($encounter);

        if ($this->isDeceased()) {
            return (new MailMessage)
                ->subject(__('Please contact :branch', ['branch' => $branch]))
                ->line(__('Hello,'))
                ->line(__('Please contact :branch regarding :patient (encounter :number) at your earliest convenience.', [
                    'branch' => $branch,
                    'patient' => $this->patientName($encounter),
                    'number' => $encounter->encounter_number,
                ]))
                ->when($encounter->branch?->contact['phone'] ?? null, fn (MailMessage $m) => $m->line(__('Phone: :phone', ['phone' => $encounter->branch->contact['phone']])))
                ->salutation(config('app.name'));
        }

        $followUp = $this->followUpAt($encounter);

        return (new MailMessage)
            ->subject($this->subject($encounter))
            ->line(__('Hello :name,', ['name' => $this->patientName($encounter)]))
            ->line($this->headline($encounter))
            ->when($encounter->discharged_at, fn (MailMessage $m) => $m->line(__('Time: :time', ['time' => $encounter->discharged_at->toDayDateTimeString()])))
            ->when($followUp, fn (MailMessage $m) => $m->line(__('Follow-up appointment: :time', ['time' => $followUp->toDayDateTimeString()])))
            ->line(__('Follow any instructions given by your care team. Contact :branch with any questions.', ['branch' => $branch]))
            ->salutation(config('app.name'));
    }

    public function toSms(object $notifiable): string
    {
        $encounter = $this->encounter->loadMissing(['branch']);
        $followUp = $this->followUpAt($encounter);

        return trim(__(':headline Encounter :number.:followup', [
            'headline' => $this->headline($encounter),
            'number' => $encounter->encounter_number,
            'followup' => $followUp ? ' '.__('Follow-up :time.', ['time' => $followUp->format('D j M H:i')]) : '',
        ]));
    }

    protected function headline(Encounter $encounter): string
    {
        $branch = $this->branchName($encounter);

        return match ($encounter->discharge_disposition) {
            DischargeDisposition::TRANSFERRED => __('You have been transferred from :branch to :destination.', [
                'branch' => $branch,
                'destination' => $encounter->transfer_destination ?: __('another facility'),
            ]),
            DischargeDisposition::REFERRED => __('You have been discharged from :branch and referred for further care.', ['branch' => $branch]),
            DischargeDisposition::AGAINST_ADVICE => __('Your stay at :branch has ended.', ['branch' => $branch]),
            default => __('You have been discharged from :branch.', ['branch' => $branch]),
        };
    }

    protected function subject(Encounter $encounter): string
    {
        return $encounter->discharge_disposition === DischargeDisposition::TRANSFERRED
            ? __('Transfer from :branch', ['branch' => $this->branchName($encounter)])
            : __('Discharged from :branch', ['branch' => $this->branchName($encounter)]);
    }

    protected function followUpAt(Encounter $encounter): ?Carbon
    {
        $value = $encounter->metadata['follow_up']['at'] ?? null;

        return filled($value) ? Carbon::parse($value) : null;
    }

    protected function isDeceased(): bool
    {
        return $this->encounter->discharge_disposition === DischargeDisposition::DECEASED;
    }
}
