<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function city(int $openweatherId, string $name): City
    {
        return City::create([
            'openweather_id' => $openweatherId,
            'name' => $name,
            'country' => 'PL',
            'latitude' => 50.0,
            'longitude' => 20.0,
        ]);
    }

    public function test_account_endpoints_require_authentication(): void
    {
        $this->getJson('/api/account')->assertUnauthorized();
        $this->putJson('/api/account/profile')->assertUnauthorized();
        $this->putJson('/api/account/password')->assertUnauthorized();
        $this->getJson('/api/account/tokens')->assertUnauthorized();
        $this->deleteJson('/api/account/tokens/1')->assertUnauthorized();
        $this->getJson('/api/account/export')->assertUnauthorized();
        $this->deleteJson('/api/account')->assertUnauthorized();
    }

    public function test_show_returns_profile_and_stats(): void
    {
        $user = User::factory()->create(['name' => 'Jan']);
        $user->cities()->attach($this->city(1, 'Kraków'));

        $this->actingAs($user, 'sanctum')->getJson('/api/account')
            ->assertOk()
            ->assertJsonPath('name', 'Jan')
            ->assertJsonPath('stats.favorites_count', 1)
            ->assertJsonStructure(['id', 'name', 'email', 'email_verified_at', 'created_at', 'settings', 'stats'])
            ->assertJsonMissingPath('password');
    }

    public function test_update_profile_name_keeps_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'jan@example.com']);

        $this->actingAs($user, 'sanctum')->putJson('/api/account/profile', ['name' => 'Janek', 'email' => 'jan@example.com'])
            ->assertOk()
            ->assertJsonPath('name', 'Janek');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_changing_email_resets_verification_and_sends_mail(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'jan@example.com']);

        $this->actingAs($user, 'sanctum')->putJson('/api/account/profile', ['name' => 'Jan', 'email' => 'nowy@example.com'])
            ->assertOk()
            ->assertJsonPath('email', 'nowy@example.com')
            ->assertJsonPath('email_verified_at', null);

        Notification::assertSentTo($user->fresh(), VerifyEmailNotification::class);
    }

    public function test_update_profile_validates_unique_email_in_polish(): void
    {
        User::factory()->create(['email' => 'zajety@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->putJson('/api/account/profile', ['name' => '', 'email' => 'zajety@example.com'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Ten adres e-mail jest już zajęty.')
            ->assertJsonPath('errors.name.0', 'Pole nazwa jest wymagane.');
    }

    public function test_change_password_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => 'starehaslo']);

        $this->actingAs($user, 'sanctum')->putJson('/api/account/password', [
            'current_password' => 'zlehaslo',
            'password' => 'nowehaslo123',
            'password_confirmation' => 'nowehaslo123',
        ])->assertStatus(422)->assertJsonPath('errors.current_password.0', 'Hasło jest nieprawidłowe.');

        $this->actingAs($user, 'sanctum')->putJson('/api/account/password', [
            'current_password' => 'starehaslo',
            'password' => 'nowehaslo123',
            'password_confirmation' => 'innehaslo123',
        ])->assertStatus(422)->assertJsonPath('errors.password.0', 'Hasła nie są zgodne.');

        $this->assertTrue(Hash::check('starehaslo', $user->fresh()->password));
    }

    public function test_change_password_revokes_other_tokens_only(): void
    {
        $user = User::factory()->create(['password' => 'starehaslo']);
        $current = $user->createToken('laptop')->plainTextToken;
        $user->createToken('telefon');
        $user->createToken('tablet');

        $this->withToken($current)->putJson('/api/account/password', [
            'current_password' => 'starehaslo',
            'password' => 'nowehaslo123',
            'password_confirmation' => 'nowehaslo123',
        ])->assertOk();

        $this->assertTrue(Hash::check('nowehaslo123', $user->fresh()->password));
        $this->assertSame(['laptop'], $user->tokens()->pluck('name')->all());

        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/user')->assertOk();
    }

    public function test_login_names_token_by_device(): void
    {
        $user = User::factory()->create(['email' => 'jan@example.com', 'password' => 'tajnehaslo']);

        $this->postJson('/api/login', ['email' => 'jan@example.com', 'password' => 'tajnehaslo', 'device_name' => 'Chrome na Windows'])->assertOk();
        $this->postJson('/api/login', ['email' => 'jan@example.com', 'password' => 'tajnehaslo'])->assertOk();

        $this->assertEqualsCanonicalizing(['Chrome na Windows', 'web'], $user->tokens()->pluck('name')->all());
    }

    public function test_tokens_list_marks_current_session(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('Firefox na Linux')->plainTextToken;
        $user->createToken('Safari na iPhone');
        User::factory()->create()->createToken('cudzy');

        $response = $this->withToken($current)->getJson('/api/account/tokens')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonStructure([['id', 'name', 'last_used_at', 'created_at', 'current']]);

        $byName = collect($response->json())->keyBy('name');
        $this->assertTrue($byName['Firefox na Linux']['current']);
        $this->assertFalse($byName['Safari na iPhone']['current']);
    }

    public function test_revoke_token_only_own(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('laptop')->plainTextToken;
        $phone = $user->createToken('telefon')->accessToken;
        $foreign = User::factory()->create()->createToken('cudzy')->accessToken;

        $this->withToken($current)->deleteJson("/api/account/tokens/{$foreign->id}")
            ->assertNotFound()
            ->assertJsonPath('error', 'Nie znaleziono sesji');
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreign->id]);

        $this->withToken($current)->deleteJson("/api/account/tokens/{$phone->id}")->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $phone->id]);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_export_returns_profile_settings_and_favorites(): void
    {
        $user = User::factory()->create(['name' => 'Jan', 'units' => 'imperial']);
        $user->cities()->attach($this->city(123, 'Rzeszów'));

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/account/export')
            ->assertOk()
            ->assertJsonPath('profile.name', 'Jan')
            ->assertJsonPath('settings.units', 'imperial')
            ->assertJsonPath('favorites.0.name', 'Rzeszów')
            ->assertJsonPath('favorites.0.openweather_id', 123)
            ->assertJsonMissingPath('profile.password');

        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
    }

    public function test_delete_account_requires_password(): void
    {
        $user = User::factory()->create(['password' => 'tajnehaslo']);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/account', ['password' => 'zlehaslo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_delete_account_removes_user_and_related_data(): void
    {
        $user = User::factory()->create(['password' => 'tajnehaslo', 'email' => 'jan@example.com']);
        $token = $user->createToken('laptop')->plainTextToken;
        $user->createToken('telefon');
        $city = $this->city(5, 'Gdańsk');
        $user->cities()->attach($city);
        DB::table('password_reset_tokens')->insert(['email' => 'jan@example.com', 'token' => 'x', 'created_at' => now()]);
        $other = User::factory()->create();
        $other->cities()->attach($city);

        $this->withToken($token)->deleteJson('/api/account', ['password' => 'tajnehaslo'])
            ->assertOk()
            ->assertJsonPath('message', 'Konto zostało usunięte.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseMissing('user_cities', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'jan@example.com']);
        // Miasto i ulubione innych użytkowników zostają
        $this->assertDatabaseHas('cities', ['id' => $city->id]);
        $this->assertDatabaseHas('user_cities', ['user_id' => $other->id]);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }
}
