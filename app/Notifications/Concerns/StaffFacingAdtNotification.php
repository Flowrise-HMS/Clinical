<?php

namespace Modules\Clinical\Notifications\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Modules\Core\Notifications\Concerns\ResolvesNotificationChannels;

/**
 * Staff-facing ADT notifications use the ADT channel setting (not the MAR one)
 * and render in the Filament bell through a Filament database payload.
 */
trait StaffFacingAdtNotification
{
    use ResolvesNotificationChannels;

    public function via(object $notifiable): array
    {
        return $this->configuredChannelsFor(
            $notifiable,
            (array) app_settings()->clinicalValue('adt_notifications_channels', config('clinical.adt_notifications.channels', ['database', 'mail'])),
        );
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
