<?php

namespace App\Notifications\Channels;

use App\Services\Push\PushNotifier;
use Illuminate\Notifications\Notification;

/**
 * Kanał powiadomień Web Push. Powiadomienie musi mieć metodę toWebPush($notifiable): array.
 */
class WebPushChannel
{
    public function __construct(protected PushNotifier $notifier)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toWebPush') || !isset($notifiable->id)) {
            return;
        }

        $this->notifier->sendToUser((int) $notifiable->id, $notification->toWebPush($notifiable));
    }
}
