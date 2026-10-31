<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.frontend_url' => 'http://front.test:5184']);
    }

    // Parametry linku z maila (id, hash, expires, signature)
    private function linkParams(User $user): array
    {
        $notification = new VerifyEmailNotification;
        $url = $notification->toMail($user)->actionUrl;
        $this->assertStringStartsWith('http://front.test:5184/verify-email?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $params);

        return $params;
    }

    public function test_register_sends_queued_verification_mail(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'Jan',
            'email' => 'jan@example.com',
            'password' => 'tajnehaslo',
            'password_confirmation' => 'tajnehaslo',
        ])->assertOk()->assertJsonPath('user.email_verified_at', null);

        $user = User::where('email', 'jan@example.com')->first();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
        $this->assertInstanceOf(ShouldQueue::class, new VerifyEmailNotification);
    }

    public function test_verify_marks_email_as_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $params = $this->linkParams($user);

        $this->assertSame(['id', 'hash', 'expires', 'signature'], array_keys($params));

        $this->actingAs($user, 'sanctum')->postJson('/api/email/verify', $params)
            ->assertOk()
            ->assertJsonPath('message', 'Adres e-mail został potwierdzony.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // Ponowne użycie linku nie jest błędem
        $this->actingAs($user->fresh(), 'sanctum')->postJson('/api/email/verify', $params)
            ->assertOk()
            ->assertJsonPath('message', 'Adres e-mail był już potwierdzony.');
    }

    public function test_verify_rejects_tampered_signature(): void
    {
        $user = User::factory()->unverified()->create();
        $params = $this->linkParams($user);

        $tampered = array_merge($params, ['signature' => str_repeat('a', 64)]);
        $this->actingAs($user, 'sanctum')->postJson('/api/email/verify', $tampered)->assertForbidden();

        $tampered = array_merge($params, ['expires' => (int) $params['expires'] + 3600]);
        $this->actingAs($user, 'sanctum')->postJson('/api/email/verify', $tampered)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verify_rejects_expired_link(): void
    {
        $user = User::factory()->unverified()->create();
        $params = $this->linkParams($user);

        $this->travel(61)->minutes();

        $this->actingAs($user, 'sanctum')->postJson('/api/email/verify', $params)
            ->assertForbidden()
            ->assertJsonPath('error', 'Link weryfikacyjny jest nieprawidłowy lub wygasł.');
    }

    public function test_verify_rejects_link_of_another_user(): void
    {
        $owner = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $params = $this->linkParams($owner);

        $this->actingAs($other, 'sanctum')->postJson('/api/email/verify', $params)->assertForbidden();

        $this->assertFalse($owner->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_verify_rejects_link_for_old_email(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'stary@example.com']);
        $params = $this->linkParams($user);
        $user->forceFill(['email' => 'nowy@example.com'])->save();

        $this->actingAs($user, 'sanctum')->postJson('/api/email/verify', $params)->assertForbidden();
    }

    public function test_verify_requires_authentication_and_params(): void
    {
        $this->postJson('/api/email/verify', [])->assertUnauthorized();

        $user = User::factory()->unverified()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/email/verify', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id', 'hash', 'expires', 'signature']);
    }

    public function test_signed_backend_route_redirects_to_frontend(): void
    {
        $user = User::factory()->unverified()->create();
        $params = $this->linkParams($user);

        $this->get("/api/email/verify/{$params['id']}/{$params['hash']}?expires={$params['expires']}&signature={$params['signature']}")
            ->assertRedirect('http://front.test:5184/verify-email?' . http_build_query($params));
    }

    public function test_resend_verification_notification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/email/verification-notification')->assertOk();
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $verified = User::factory()->create();
        $this->actingAs($verified, 'sanctum')->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Adres e-mail jest już potwierdzony.');
        Notification::assertNotSentTo($verified, VerifyEmailNotification::class);
    }

    public function test_resend_is_throttled(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user, 'sanctum')->postJson('/api/email/verification-notification')->assertOk();
        }

        $this->actingAs($user, 'sanctum')->postJson('/api/email/verification-notification')->assertStatus(429);
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 3);
    }

    public function test_unverified_user_can_still_use_the_app(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email_verified_at', null);
        $this->actingAs($user, 'sanctum')->getJson('/api/user/settings')->assertOk();
        $this->actingAs($user, 'sanctum')->getJson('/api/cities/user')->assertOk();
    }
}
