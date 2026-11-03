<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Notifications\Notification;

/**
 * Shared shape for every in-app notification.
 *
 * Written synchronously through Laravel's own database channel (the
 * `notifications` table) so the feed updates the moment the event happens;
 * the FCM channel queues the push separately once Firebase is configured.
 */
abstract class BaseAppNotification extends Notification
{
    /** Short machine key the mobile app switches on, e.g. "bill_due". */
    abstract public function type(): string;

    abstract public function title(): string;

    public function body(): ?string
    {
        return null;
    }

    /** Extra payload the app uses for deep links. */
    public function payload(): array
    {
        return [];
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('fcm.enabled') && $notifiable->notifications_enabled) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type(),
            'title' => $this->title(),
            'body' => $this->body(),
            'payload' => $this->payload(),
        ];
    }

    /**
     * Message handed to the FCM channel.
     *
     * @return array{title: string, body: ?string, data: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        // FCM data values must be strings.
        $data = ['type' => $this->type()];

        foreach ($this->payload() as $key => $value) {
            $data[$key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return [
            'title' => $this->title(),
            'body' => $this->body() ?? '',
            'data' => $data,
        ];
    }
}
