<?php

namespace Modules\Clinical\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Clinical\Enums\AdmissionRequestStatus;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Models\AdmissionRequest;
use Modules\Clinical\Notifications\Concerns\StaffFacingAdtNotification;

/**
 * Tells the requesting clinician how the ward answered their request.
 */
class AdmissionDecidedNotification extends Notification implements ShouldQueue
{
    use StaffFacingAdtNotification;

    public function __construct(protected AdmissionRequest $request) {}

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->request->loadMissing(['patient', 'requestedWard', 'assignedBed', 'decider']);

        return (new MailMessage)
            ->subject($this->title())
            ->line($this->body())
            ->when($request->decision_notes, fn (MailMessage $m) => $m->line(__('Notes: :notes', ['notes' => $request->decision_notes])))
            ->action(__('Open patient'), $this->url());
    }

    public function toSms(object $notifiable): string
    {
        return $this->title().' — '.$this->body();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->databasePayload(
            $this->title(),
            $this->body(),
            match ($this->request->status) {
                AdmissionRequestStatus::Accepted => 'success',
                AdmissionRequestStatus::Rejected => 'danger',
                default => 'warning',
            },
            $this->url(),
            ['admission_request_id' => $this->request->id, 'encounter_id' => $this->request->encounter_id, 'patient_id' => $this->request->patient_id],
        );
    }

    protected function title(): string
    {
        $request = $this->request->loadMissing(['requestedWard']);
        $ward = $request->requestedWard?->name ?? __('ward');

        return match ($request->status) {
            AdmissionRequestStatus::Accepted => __('Admission accepted — :ward', ['ward' => $ward]),
            AdmissionRequestStatus::Rejected => __('Admission rejected — :ward', ['ward' => $ward]),
            AdmissionRequestStatus::Expired => __('Admission request expired — :ward', ['ward' => $ward]),
            default => __('Admission request closed — :ward', ['ward' => $ward]),
        };
    }

    protected function body(): string
    {
        $request = $this->request->loadMissing(['patient', 'assignedBed', 'decider']);
        $patient = $request->patient?->full_name ?? __('The patient');

        return match ($request->status) {
            AdmissionRequestStatus::Accepted => __(':patient admitted to :bed by :by.', [
                'patient' => $patient,
                'bed' => $request->assignedBed?->name ?? __('a bed'),
                'by' => $request->decider?->name ?? __('ward staff'),
            ]),
            AdmissionRequestStatus::Rejected => __(':patient was not admitted: :reason', [
                'patient' => $patient,
                'reason' => $request->decision_notes ?: __('no reason given'),
            ]),
            AdmissionRequestStatus::Expired => __('The request for :patient was not answered in time. Request again if a bed is still needed.', ['patient' => $patient]),
            default => __('The request for :patient is closed.', ['patient' => $patient]),
        };
    }

    protected function url(): string
    {
        return ClinicalWorkspace::getUrl(['patientId' => $this->request->patient_id]);
    }
}
