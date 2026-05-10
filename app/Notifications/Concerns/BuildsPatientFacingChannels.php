<?php

namespace Modules\Clinical\Notifications\Concerns;

trait BuildsPatientFacingChannels
{
    protected function channelsFor(object $notifiable): array
    {
        $channels = [];

        $mail = method_exists($notifiable, 'routeNotificationForMail')
            ? $notifiable->routeNotificationForMail($this)
            : ($notifiable->email ?? null);

        if ($mail) {
            $channels[] = 'mail';
        }

        $sms = method_exists($notifiable, 'routeNotificationForSms')
            ? $notifiable->routeNotificationForSms($this)
            : ($notifiable->phone ?? null);

        if ($sms) {
            $channels[] = 'sms';
        }

        return $channels;
    }
}
