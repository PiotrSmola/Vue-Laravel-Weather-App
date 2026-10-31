<?php

namespace App\Services\Push;

use App\Models\PushSubscription;

/**
 * Wysyła powiadomienie na wszystkie urządzenia użytkownika i usuwa wygasłe subskrypcje.
 */
class PushNotifier
{
    public function __construct(protected WebPushSender $sender)
    {
    }

    public function isConfigured(): bool
    {
        return $this->sender->isConfigured();
    }

    /**
     * Payload: {title, body, url?, tag?, icon?}. Zwraca liczniki ['sent' =>, 'expired' =>, 'failed' =>].
     */
    public function sendToUser(int $userId, array $payload): array
    {
        $subscriptions = PushSubscription::where('user_id', $userId)->get();
        $counts = [WebPushSender::SENT => 0, WebPushSender::EXPIRED => 0, WebPushSender::FAILED => 0];

        if ($subscriptions->isEmpty()) {
            return $counts;
        }

        $payload += ['icon' => '/pwa-192x192.png', 'badge' => '/pwa-badge-96x96.png', 'url' => '/'];
        $results = $this->sender->send($subscriptions, $payload);
        $expired = [];

        foreach ($results as $id => $status) {
            $counts[$status] = ($counts[$status] ?? 0) + 1;

            if ($status === WebPushSender::EXPIRED) {
                $expired[] = $id;
            }
        }

        if ($expired) {
            PushSubscription::whereIn('id', $expired)->delete();
        }

        return $counts;
    }
}
