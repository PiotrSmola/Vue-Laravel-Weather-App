<?php

namespace Tests\Feature;

use App\Models\AlertEvent;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Alerts\AlertEvaluator;
use App\Services\Push\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesAlerts;
use Tests\Fakes\FakePushSender;
use Tests\TestCase;

class PushNotificationsTest extends TestCase
{
    use RefreshDatabase, CreatesAlerts;

    protected FakePushSender $push;

    protected function setUp(): void
    {
        parent::setUp();

        config(['webpush.public_key' => 'test-public-key', 'webpush.private_key' => 'test-private-key']);
        $this->push = FakePushSender::install($this->app);
    }

    protected function subscriptionPayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'BPublicKeyExample', 'auth' => 'authSecret'],
            'content_encoding' => 'aes128gcm',
        ];
    }

    protected function subscribe(User $user, string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): PushSubscription
    {
        return PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'public_key' => 'BPublicKeyExample',
            'auth_token' => 'authSecret',
        ]);
    }

    public function test_public_key_endpoint(): void
    {
        $this->getJson('/api/push/public-key')->assertOk()->assertExactJson(['public_key' => 'test-public-key']);

        $this->push->configured = false;
        $this->getJson('/api/push/public-key')
            ->assertStatus(503)
            ->assertJsonPath('error', 'Powiadomienia push nie są skonfigurowane na serwerze');
    }

    public function test_subscribe_update_and_unsubscribe(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/push/subscriptions', $this->subscriptionPayload())->assertCreated();

        // Ponowna rejestracja tego samego urządzenia aktualizuje klucze
        $payload = $this->subscriptionPayload();
        $payload['keys']['auth'] = 'newAuth';
        $this->postJson('/api/push/subscriptions', $payload)->assertOk();

        $subscription = PushSubscription::sole();
        $this->assertSame('newAuth', $subscription->auth_token);
        $this->assertSame($user->id, $subscription->user_id);

        $this->json('DELETE', '/api/push/subscriptions', ['endpoint' => $payload['endpoint']])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_device_moves_to_new_account(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->subscribe($first);
        Sanctum::actingAs($second);

        $this->postJson('/api/push/subscriptions', $this->subscriptionPayload())->assertOk();

        $this->assertSame($second->id, PushSubscription::sole()->user_id);
    }

    public function test_cannot_unsubscribe_other_users_device(): void
    {
        $owner = User::factory()->create();
        $this->subscribe($owner);
        Sanctum::actingAs(User::factory()->create());

        $this->json('DELETE', '/api/push/subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])->assertOk();

        $this->assertSame(1, PushSubscription::count());
    }

    public function test_subscription_validation_and_auth(): void
    {
        $this->postJson('/api/push/subscriptions', $this->subscriptionPayload())->assertUnauthorized();
        $this->json('DELETE', '/api/push/subscriptions')->assertUnauthorized();
        $this->postJson('/api/push/test')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/push/subscriptions', [])->assertJsonValidationErrors(['endpoint', 'keys']);
        $this->postJson('/api/push/subscriptions', $this->subscriptionPayload('http://insecure.example/push'))
            ->assertJsonValidationErrors('endpoint');
        $this->postJson('/api/push/subscriptions', ['endpoint' => 'https://push.example/x', 'keys' => ['p256dh' => 'x']])
            ->assertJsonValidationErrors('keys.auth');
        $this->postJson('/api/push/subscriptions', array_merge($this->subscriptionPayload(), ['content_encoding' => 'gzip']))
            ->assertJsonValidationErrors('content_encoding');
        $this->json('DELETE', '/api/push/subscriptions', [])->assertJsonValidationErrors('endpoint');
    }

    public function test_subscriptions_limit_removes_oldest(): void
    {
        config(['webpush.subscriptions_limit' => 2]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach (['a', 'b', 'c'] as $i => $suffix) {
            $this->travel($i)->minutes();
            $this->postJson('/api/push/subscriptions', $this->subscriptionPayload('https://push.example/' . $suffix));
        }

        $this->assertEqualsCanonicalizing(
            ['https://push.example/b', 'https://push.example/c'],
            PushSubscription::pluck('endpoint')->all()
        );
    }

    public function test_send_test_notification(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/push/test')
            ->assertStatus(422)
            ->assertJsonPath('error', 'Brak urządzeń z włączonymi powiadomieniami push');

        $this->subscribe($user);
        $this->subscribe($user, 'https://updates.push.services.mozilla.com/wpush/v2/xyz');

        $this->postJson('/api/push/test')
            ->assertOk()
            ->assertJsonPath('counts.sent', 2);

        $this->assertCount(1, $this->push->sent);
        $this->assertSame('/alerts', $this->push->sent[0]['payload']['url']);
        $this->assertSame('/pwa-192x192.png', $this->push->sent[0]['payload']['icon']);
    }

    public function test_test_notification_when_push_not_configured(): void
    {
        $this->push->configured = false;
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/push/test')->assertStatus(503);
    }

    public function test_alert_is_delivered_by_push_and_expired_subscriptions_are_removed(): void
    {
        Mail::fake();
        [$user, $city] = $this->userWithFavorite();
        $active = $this->subscribe($user);
        $expired = $this->subscribe($user, 'https://push.example/gone');
        $this->push->statuses[$expired->id] = WebPushSender::EXPIRED;
        $this->makeRule($user, $city, ['type' => 'humidity_above', 'threshold' => 80, 'channels' => ['push']]);

        app(AlertEvaluator::class)->evaluateCurrent($city, $this->makeMeasurement($city, ['humidity' => 92]));

        $this->assertCount(1, $this->push->sent);
        $payload = $this->push->sent[0]['payload'];
        $this->assertSame('Rzeszów: Wysoka wilgotność', $payload['title']);
        $this->assertSame('/weather/759734', $payload['url']);
        $this->assertSame(AlertEvent::sole()->id, $payload['event_id']);

        $this->assertNotNull($active->fresh());
        $this->assertNull($expired->fresh());
    }

    public function test_failed_push_keeps_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscribe($user);
        $this->push->statuses[$subscription->id] = WebPushSender::FAILED;
        Sanctum::actingAs($user);

        $this->postJson('/api/push/test')->assertStatus(502);

        $this->assertNotNull($subscription->fresh());
    }

    public function test_alert_mail_is_sent_through_mail_channel(): void
    {
        [$user, $city] = $this->userWithFavorite();
        $this->makeRule($user, $city, ['type' => 'pressure_below', 'threshold' => 1000, 'channels' => ['mail']]);

        app(AlertEvaluator::class)->evaluateCurrent($city, $this->makeMeasurement($city, ['pressure' => 985]));

        // MAIL_MAILER=array w testach - sprawdzamy faktycznie wysłaną wiadomość
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages->first()->getOriginalMessage();
        $this->assertSame('Alert pogodowy: Rzeszów: Niskie ciśnienie', $email->getSubject());
        $this->assertSame($user->email, $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('985 hPa', $email->getHtmlBody());
        $this->assertCount(0, $this->push->sent);
    }
}
