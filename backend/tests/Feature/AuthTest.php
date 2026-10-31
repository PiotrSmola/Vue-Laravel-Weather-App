<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'tajnehaslo',
            'password_confirmation' => 'tajnehaslo',
        ], $overrides);
    }

    public function test_register_returns_user_and_token(): void
    {
        $this->postJson('/api/register', $this->registerPayload())
            ->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token'])
            ->assertJsonMissingPath('user.password');
    }

    public function test_register_validation(): void
    {
        $this->postJson('/api/register', $this->registerPayload(['password' => 'krotkie', 'password_confirmation' => 'krotkie']))
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/api/register', $this->registerPayload(['password_confirmation' => 'inne-haslo']))
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/api/register', $this->registerPayload(['name' => str_repeat('a', 256)]))
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->postJson('/api/register', $this->registerPayload(['email' => 'nie-email']))
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'jan@example.com']);

        $this->postJson('/api/register', $this->registerPayload())
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_success(): void
    {
        User::factory()->create(['email' => 'jan@example.com', 'password' => 'tajnehaslo']);

        $this->postJson('/api/login', ['email' => 'jan@example.com', 'password' => 'tajnehaslo'])
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_does_not_reveal_whether_email_exists(): void
    {
        User::factory()->create(['email' => 'jan@example.com', 'password' => 'tajnehaslo']);

        $wrongPassword = $this->postJson('/api/login', ['email' => 'jan@example.com', 'password' => 'zlehaslo']);
        $unknownEmail = $this->postJson('/api/login', ['email' => 'nikt@example.com', 'password' => 'zlehaslo']);

        $wrongPassword->assertStatus(401);
        $unknownEmail->assertStatus(401);
        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
        $unknownEmail->assertJsonPath('errors.email.0', 'Nieprawidłowe dane logowania.');
    }

    public function test_login_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'jan@example.com', 'password' => 'zlehaslo'])->assertStatus(401);
        }

        $this->postJson('/api/login', ['email' => 'jan@example.com', 'password' => 'zlehaslo'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Zbyt wiele prób. Spróbuj ponownie za chwilę.');
    }

    public function test_register_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/register', $this->registerPayload(['password' => 'x']));
        }

        $this->postJson('/api/register', $this->registerPayload())->assertStatus(429);
    }

    public function test_logout_deletes_only_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('a')->plainTextToken;
        $other = $user->createToken('b')->plainTextToken;

        $this->withToken($current)->postJson('/api/logout')->assertOk();

        $this->assertSame(1, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/user')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_all_deletes_all_tokens(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('a')->plainTextToken;
        $user->createToken('b');

        $this->withToken($current)->postJson('/api/logout-all')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_tokens_expire(): void
    {
        $this->assertSame(60 * 24 * 30, config('sanctum.expiration'));

        $user = User::factory()->create();
        $token = $user->createToken('a')->plainTextToken;

        $this->travel(31)->days();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }
}
