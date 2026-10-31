<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const DEFAULTS = ['units' => 'metric', 'wind_unit' => 'kmh', 'lang' => 'pl', 'home_city_id' => null];

    public function test_get_default_settings(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/user/settings')->assertOk()->assertExactJson(self::DEFAULTS);
    }

    public function test_settings_for_user_created_via_register(): void
    {
        $token = $this->postJson('/api/register', [
            'name' => 'Jan', 'email' => 'jan@example.com',
            'password' => 'tajnehaslo', 'password_confirmation' => 'tajnehaslo',
        ])->json('token');

        $this->withToken($token)->getJson('/api/user/settings')->assertExactJson(self::DEFAULTS);
    }

    public function test_partial_update_returns_full_settings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/user/settings', ['units' => 'imperial', 'home_city_id' => 759734])
            ->assertOk()
            ->assertExactJson(['units' => 'imperial', 'wind_unit' => 'kmh', 'lang' => 'pl', 'home_city_id' => 759734]);

        $this->putJson('/api/user/settings', ['wind_unit' => 'mph', 'lang' => 'en'])
            ->assertOk()
            ->assertExactJson(['units' => 'imperial', 'wind_unit' => 'mph', 'lang' => 'en', 'home_city_id' => 759734]);

        $this->putJson('/api/user/settings', ['home_city_id' => null])
            ->assertOk()
            ->assertJsonPath('home_city_id', null);

        $this->assertSame('mph', $user->fresh()->wind_unit);
    }

    public function test_update_validation(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/user/settings', ['units' => 'kelvin'])->assertStatus(422)->assertJsonValidationErrors('units');
        $this->putJson('/api/user/settings', ['wind_unit' => 'knots'])->assertStatus(422)->assertJsonValidationErrors('wind_unit');
        $this->putJson('/api/user/settings', ['lang' => 'de'])->assertStatus(422)->assertJsonValidationErrors('lang');
        $this->putJson('/api/user/settings', ['home_city_id' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('home_city_id');
    }

    public function test_user_endpoint_includes_settings(): void
    {
        $user = User::factory()->create(['lang' => 'en']);
        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email)
            ->assertJsonPath('settings', ['units' => 'metric', 'wind_unit' => 'kmh', 'lang' => 'en', 'home_city_id' => null])
            ->assertJsonMissingPath('password');
    }

    public function test_settings_require_authentication(): void
    {
        $this->getJson('/api/user/settings')->assertUnauthorized();
        $this->putJson('/api/user/settings', ['units' => 'imperial'])->assertUnauthorized();
    }
}
