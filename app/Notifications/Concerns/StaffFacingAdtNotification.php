<?php

namespace Modules\Clinical\Notifications\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;

/**
 * Staff-facing ADT notifications use the ADT channel list (not the MAR one)
 * and render in the Filament bell through a Filament database payload.
 */
trait StaffFacingAdtNotification
{
    use BuildsStaffFacingChannels;

    public function via(object $notifiable): array
    {
        return $this->staffChannelsFor($notifiable, (array) config('clinical.adt_notifications.channels', ['database', 'mail']));
    }

    /**
     * @return array<string, mixed>
     */
    protected function databasePayload(string $title, ?string $body, string $status, ?string $url = null, array $extra = []): array
    {
        $notification = FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->status($status)
            ->icon(match ($status) {
                'success' => 'heroicon-o-check-circle',
                'danger' => 'heroicon-o-x-circle',
                'warning' => 'heroicon-o-exclamation-triangle',
                default => 'heroicon-o-information-circle',
            });

        if ($url !== null) {
            $notification->actions([
                Action::make('open')->label(__('Open'))->url($url)->markAsRead(),
            ]);
        }

        return array_merge($notification->getDatabaseMessage(), $extra);
    }
}
