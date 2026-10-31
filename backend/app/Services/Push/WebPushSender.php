<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Wysyłka Web Push przez minishlink/web-push (VAPID z config/webpush.php).
 * W testach podmieniana na atrapę: $this->app->instance(WebPushSender::class, $fake).
 */
class WebPushSender
{
    public const SENT = 'sent';
    public const EXPIRED = 'expired'; // 404 / 410 z usługi push - subskrypcja do usunięcia
    public const FAILED = 'failed';

    // Czy skonfigurowano klucze VAPID
    public function isConfigured(): bool
    {
        return config('webpush.public_key') !== '' && config('webpush.public_key') !== null
            && config('webpush.private_key') !== '' && config('webpush.private_key') !== null;
    }

    /**
     * @param  iterable<PushSubscription>  $subscriptions
     * @return array<int, string>  id subskrypcji => SENT | EXPIRED | FAILED
     */
    public function send(iterable $subscriptions, array $payload): array
    {
        $results = [];

        if (!$this->isConfigured()) {
            foreach ($subscriptions as $subscription) {
                $results[$subscription->id] = self::FAILED;
            }

            return $results;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ],
        ], ['TTL' => (int) config('webpush.ttl', 21600), 'urgency' => 'normal'], 10);

        $byEndpoint = [];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($subscriptions as $subscription) {
            /** @var PushSubscription $subscription */
            try {
                $webPush->queueNotification(new Subscription(
                    $subscription->endpoint,
                    $subscription->public_key,
                    $subscription->auth_token,
                    $subscription->content_encoding ?: 'aes128gcm',
                ), $body);
                $byEndpoint[$subscription->endpoint] = $subscription->id;
                $results[$subscription->id] = self::FAILED;
            } catch (\Throwable $e) {
                $results[$subscription->id] = self::FAILED;
                Log::warning('Nieprawidłowa subskrypcja push ' . $subscription->id . ': ' . $e->getMessage());
            }
        }

        if (!$byEndpoint) {
            return $results;
        }

        try {
            foreach ($webPush->flush() as $report) {
                $id = $byEndpoint[$report->getEndpoint()] ?? null;

                if ($id === null) {
                    continue;
                }

                if ($report->isSuccess()) {
                    $results[$id] = self::SENT;
                } elseif ($report->isSubscriptionExpired()) {
                    $results[$id] = self::EXPIRED;
                } else {
                    $results[$id] = self::FAILED;
                    Log::warning('Nie udało się wysłać powiadomienia push ' . $id . ': ' . $report->getReason());
                }
            }
        } catch (\Throwable $e) {
            Log::error('Błąd wysyłki powiadomień push: ' . $e->getMessage());
        }

        return $results;
    }
}
