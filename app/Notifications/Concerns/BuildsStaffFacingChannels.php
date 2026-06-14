<?php

namespace Modules\Clinical\Notifications\Concerns;

trait BuildsStaffFacingChannels
{
    protected function staffChannelsFor(object $notifiable): array
    {
        $configured = config('clinical.mar_reminders.channels', ['database']);
        $channels = [];

        if (in_array('database', $configured, true)) {
            $channels[] = 'database';
        }

        $mail = method_exists($notifiable, 'routeNotificationForMail')
            ? $notifiable->routeNotificationForMail($this)
            : ($notifiable->email ?? null);

        if ($mail && in_array('mail', $configured, true)) {
            $channels[] = 'mail';
        }

        $sms = method_exists($notifiable, 'routeNotificationForSms')
            ? $notifiable->routeNotificationForSms($this)
            : ($notifiable->phone ?? null);

        if ($sms && in_array('sms', $configured, true)) {
            $channels[] = 'sms';
        }

        return $channels;
    }
}
