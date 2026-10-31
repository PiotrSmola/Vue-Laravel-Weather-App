<?php

namespace Tests\Feature;

use App\Http\Controllers\PasswordResetController;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.frontend_url' => 'http://front.test:5184,http://other.test']);
    }

    public function test_forgot_password_sends_notification_with_frontend_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'jan@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'jan@example.com'])
            ->assertOk()
            ->assertJsonPath('message', PasswordResetController::SENT_MESSAGE);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);
            $this->assertSame('Resetowanie hasła', $mail->subject);
            $this->assertStringStartsWith('http://front.test:5184/reset-password?token=', $mail->actionUrl);
            $this->assertStringContainsString('email=jan%40example.com', $mail->actionUrl);

            return true;
        });
    }

    public function test_forgot_password_does_not_reveal_whether_email_exists(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'jan@example.com']);

        $known = $this->postJson('/api/forgot-password', ['email' => 'jan@example.com']);
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'nikt@example.com']);
        // Drugie żądanie dla tego samego adresu jest blokowane przez broker - odpowiedź taka sama
        $repeated = $this->postJson('/api/forgot-password', ['email' => 'jan@example.com']);

        $known->assertOk();
        $unknown->assertOk();
        $repeated->assertOk();
        $this->assertSame($known->json(), $unknown->json());
        $this->assertSame($known->json(), $repeated->json());
        Notification::assertSentTimes(ResetPasswordNotification::class, 1);
    }

    public function test_forgot_password_validates_email_in_polish(): void
    {
        $this->postJson('/api/forgot-password', ['email' => 'nie-email'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Pole adres e-mail musi być prawidłowym adresem e-mail.');
    }

    public function test_forgot_password_is_throttled(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/forgot-password', ['email' => "user{$i}@example.com"])->assertOk();
        }

        $this->postJson('/api/forgot-password', ['email' => 'kolejny@example.com'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Zbyt wiele prób. Spróbuj ponownie za chwilę.');
    }

    public function test_reset_password_changes_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create(['email' => 'jan@example.com']);
        $user->createToken('telefon');
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'jan@example.com',
            'password' => 'nowehaslo123',
            'password_confirmation' => 'nowehaslo123',
        ])->assertOk()->assertJsonPath('message', __('passwords.reset'));

        $this->assertTrue(Hash::check('nowehaslo123', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());

        // Token jest jednorazowy
        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'jan@example.com',
            'password' => 'innehaslo123',
            'password_confirmation' => 'innehaslo123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_reset_password_rejects_invalid_token_and_unknown_email_identically(): void
    {
        User::factory()->create(['email' => 'jan@example.com']);
        $payload = ['token' => 'zly-token', 'password' => 'nowehaslo123', 'password_confirmation' => 'nowehaslo123'];

        $badToken = $this->postJson('/api/reset-password', $payload + ['email' => 'jan@example.com']);
        $unknown = $this->postJson('/api/reset-password', $payload + ['email' => 'nikt@example.com']);

        $badToken->assertStatus(422)->assertJsonPath('errors.email.0', 'Link do resetowania hasła jest nieprawidłowy lub wygasł.');
        $this->assertSame($badToken->json(), $unknown->json());
    }

    public function test_reset_password_validates_password(): void
    {
        $this->postJson('/api/reset-password', [
            'token' => 'x',
            'email' => 'jan@example.com',
            'password' => 'krotkie',
            'password_confirmation' => 'krotkie',
        ])->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'Pole hasło musi mieć co najmniej 8 znaków.');
    }
}
