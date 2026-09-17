<?php

namespace Modules\Clinical\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\WardBoard;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterLocationEvent;
use Modules\Clinical\Notifications\Concerns\StaffFacingAdtNotification;

/**
 * Tells the receiving ward a patient has been moved into one of its beds.
 */
class PatientTransferredWardNotification extends Notification implements ShouldQueue
{
    use StaffFacingAdtNotification;

    public function __construct(protected Encounter $encounter, protected EncounterLocationEvent $event) {}

    public function toMail(object $notifiable): MailMessage
    {
        $encounter = $this->encounter->loadMissing(['patient', 'location', 'bed']);

        return (new MailMessage)
            ->subject(__('Incoming transfer — :ward', ['ward' => $encounter->location?->name ?? __('your ward')]))
            ->line($this->body())
            ->action(__('Open ward board'), $this->url());
    }

    public function toSms(object $notifiable): string
    {
        return $this->body();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $encounter = $this->encounter->loadMissing(['location']);

        return $this->databasePayload(
            __('Incoming transfer — :ward', ['ward' => $encounter->location?->name ?? __('ward')]),
            $this->body(),
            'info',
            $this->url(),
            ['encounter_id' => $encounter->id, 'patient_id' => $encounter->patient_id, 'location_event_id' => $this->event->id],
        );
    }

    protected function body(): string
    {
        $encounter = $this->encounter->loadMissing(['patient', 'location', 'bed']);
        $this->event->loadMissing('fromLocation');

        return __(':patient moved to :ward :bed from :from.', [
            'patient' => $encounter->patient?->full_name ?? __('A patient'),
            'ward' => $encounter->location?->name ?? __('this ward'),
            'bed' => $encounter->bed?->name ? '('.$encounter->bed->name.')' : '',
            'from' => $this->event->fromLocation?->name ?? __('another ward'),
        ]);
    }

    protected function url(): string
    {
        return WardBoard::getUrl(['ward' => $this->encounter->location_id]);
    }
}
