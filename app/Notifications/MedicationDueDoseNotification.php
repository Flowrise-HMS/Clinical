<?php

namespace Modules\Clinical\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Notifications\Concerns\BuildsStaffFacingChannels;
use Modules\Pharmacy\Classes\Data\DoseSlot;

class MedicationDueDoseNotification extends Notification implements ShouldQueue
{
    use BuildsStaffFacingChannels, Queueable;

    public function __construct(
        protected RequestItem $requestItem,
        protected DoseSlot $slot,
        protected string $reminderType,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->staffChannelsFor($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $item = $this->requestItem->loadMissing(['service', 'serviceRequest.patient']);
        $client = $item->serviceRequest?->clientIdentity()->name ?? 'N/A';
        $drug = $item->service?->name ?? 'Medication';

        $subject = match ($this->reminderType) {
            'overdue' => __('Overdue medication dose: :drug', ['drug' => $drug]),
            'due_now', 'due_soon' => __('Medication due: :drug', ['drug' => $drug]),
            default => __('Medication reminder: :drug', ['drug' => $drug]),
        };

        return (new MailMessage)
            ->subject($subject)
            ->line(__('Client: :client', ['client' => $client]))
            ->line(__('Medication: :drug', ['drug' => $drug]))
            ->line(__('Due at: :time', ['time' => $this->slot->dueAt->toDayDateTimeString()]))
            ->line(__('Please record the dose in the MAR.'));
    }

    public function toSms(object $notifiable): string
    {
        $item = $this->requestItem->loadMissing(['service', 'serviceRequest.patient']);
        $client = $item->serviceRequest?->clientIdentity()->name ?? 'N/A';
        $drug = $item->service?->name ?? 'Medication';

        return __('MAR: :drug for :client due :time', [
            'drug' => $drug,
            'client' => $client,
            'time' => $this->slot->dueAt->format('H:i'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $item = $this->requestItem->loadMissing(['service', 'serviceRequest.patient']);
        $clientIdentity = $item->serviceRequest?->clientIdentity();

        return [
            'request_item_id' => $item->id,
            'patient_id' => $item->serviceRequest?->patient_id,
            'patient_name' => $clientIdentity?->name,
            'medication' => $item->service?->name,
            'due_at' => $this->slot->dueAt->toIso8601String(),
            'reminder_type' => $this->reminderType,
            'title' => match ($this->reminderType) {
                'overdue' => 'Overdue medication dose',
                'due_now' => 'Medication due now',
                default => 'Medication due soon',
            },
        ];
    }
}
