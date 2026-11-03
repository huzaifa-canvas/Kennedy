<?php

namespace App\Notifications\Channels;

use App\Jobs\SendFcmMessage;
use Illuminate\Notifications\Notification;

/**
 * Hands the push off to a queued job. The database channel has already
 * written the in-app copy by the time this runs, so the feed never waits
 * on Firebase.
 */
class FcmChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! config('fcm.enabled') || ! method_exists($notification, 'toFcm')) {
            return;
        }

        if (! $notifiable->devices()->exists()) {
            return;
        }

        SendFcmMessage::dispatch($notifiable->getKey(), $notification->toFcm($notifiable));
    }
}
