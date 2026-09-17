<?php

namespace Modules\Clinical\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\WardBoard;
use Modules\Clinical\Models\AdmissionRequest;
use Modules\Clinical\Notifications\Concerns\StaffFacingAdtNotification;

/**
 * Tells ward staff a clinician wants a patient admitted to their ward.
 */
class AdmissionRequestedNotification extends Notification implements ShouldQueue
{
    use StaffFacingAdtNotification;

    public function __construct(protected AdmissionRequest $request) {}

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->request->loadMissing(['patient', 'requestedWard', 'requestedBed', 'requester', 'encounter']);

        return (new MailMessage)
            ->subject(__('Admission request for :ward', ['ward' => $request->requestedWard?->name ?? __('your ward')]))
            ->line(__(':patient (:number) needs a bed in :ward.', [
                'patient' => $request->patient?->full_name ?? __('A patient'),
                'number' => $request->encounter?->encounter_number,
                'ward' => $request->requestedWard?->name ?? __('your ward'),
            ]))
            ->when($request->requestedBed, fn (MailMessage $m) => $m->line(__('Preferred bed: :bed', ['bed' => $request->requestedBed->name])))
            ->when($request->requester, fn (MailMessage $m) => $m->line(__('Requested by: :name', ['name' => $request->requester->name])))
            ->when($request->notes, fn (MailMessage $m) => $m->line(__('Notes: :notes', ['notes' => $request->notes])))
            ->when($request->expires_at, fn (MailMessage $m) => $m->line(__('Expires: :time', ['time' => $request->expires_at->toDayDateTimeString()])))
            ->action(__('Open ward board'), $this->url());
    }

    public function toSms(object $notifiable): string
    {
        $request = $this->request->loadMissing(['patient', 'requestedWard']);

        return __('Admission request: :patient to :ward.', [
            'patient' => $request->patient?->full_name ?? __('patient'),
            'ward' => $request->requestedWard?->name ?? __('ward'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $request = $this->request->loadMissing(['patient', 'requestedWard', 'requester']);

        return $this->databasePayload(
            __('Admission request — :ward', ['ward' => $request->requestedWard?->name ?? __('ward')]),
            __(':patient, requested by :by', ['patient' => $request->patient?->full_name ?? __('A patient'), 'by' => $request->requester?->name ?? __('a clinician')]),
            'warning',
            $this->url(),
            ['admission_request_id' => $request->id, 'encounter_id' => $request->encounter_id, 'patient_id' => $request->patient_id],
        );
    }

    protected function url(): string
    {
        return WardBoard::getUrl(['ward' => $this->request->requested_ward_id]);
    }
}
