<?php

namespace Tests\Fakes;

use App\Services\Push\WebPushSender;

/**
 * Atrapa wysyłki Web Push - zapamiętuje wysłane powiadomienia, nie łączy się z usługami push.
 *
 *   $push = FakePushSender::install($this->app);
 *   $push->statuses[$subscriptionId] = WebPushSender::EXPIRED;   // symulacja 404/410
 */
class FakePushSender extends WebPushSender
{
    /** @var array<int, array{subscription_ids: array<int>, payload: array}> */
    public array $sent = [];

    /** @var array<int, string> Wymuszone statusy dla subskrypcji (domyślnie SENT) */
    public array $statuses = [];

    public bool $configured = true;

    public static function install($app): self
    {
        $fake = new self();
        $app->instance(WebPushSender::class, $fake);

        return $fake;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function send(iterable $subscriptions, array $payload): array
    {
        $results = [];

        foreach ($subscriptions as $subscription) {
            $results[$subscription->id] = $this->statuses[$subscription->id] ?? self::SENT;
        }

        $this->sent[] = ['subscription_ids' => array_keys($results), 'payload' => $payload];

        return $results;
    }
}
