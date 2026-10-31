<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesAlerts;
use Tests\TestCase;

class AlertRulesTest extends TestCase
{
    use RefreshDatabase, CreatesAlerts;

    public function test_routes_require_authentication(): void
    {
        $this->getJson('/api/alerts')->assertUnauthorized();
        $this->postJson('/api/alerts', [])->assertUnauthorized();
        $this->putJson('/api/alerts/1', [])->assertUnauthorized();
        $this->deleteJson('/api/alerts/1')->assertUnauthorized();
        $this->getJson('/api/alerts/events')->assertUnauthorized();
        $this->getJson('/api/alerts/meta')->assertUnauthorized();
    }

    public function test_create_rule_for_favorite_city(): void
    {
        [$user, $city] = $this->userWithFavorite();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/alerts', [
            'city_id' => 759734,
            'type' => 'temp_below',
            'threshold' => -5.5,
            'channels' => ['push', 'mail'],
            'label' => '  Mróz  ',
        ])->assertCreated();

        $response->assertJsonPath('data.city_id', 759734)
            ->assertJsonPath('data.city.name', 'Rzeszów')
            ->assertJsonPath('data.type', 'temp_below')
            ->assertJsonPath('data.threshold', -5.5)
            ->assertJsonPath('data.channels', ['mail', 'push'])
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.cooldown_minutes', 360)
            ->assertJsonPath('data.label', 'Mróz');

        $rule = AlertRule::first();
        $this->assertSame($city->id, $rule->city_id);
        $this->assertSame($user->id, $rule->user_id);
    }

    public function test_create_condition_and_forecast_rules(): void
    {
        [$user] = $this->userWithFavorite();
        Sanctum::actingAs($user);

        $this->postJson('/api/alerts', [
            'city_id' => 759734, 'type' => 'condition', 'condition' => 'thunderstorm',
            'threshold' => 12, 'channels' => ['mail'],
        ])->assertCreated()
            ->assertJsonPath('data.condition', 'thunderstorm')
            ->assertJsonPath('data.threshold', null);

        // Alerty prognozy mają domyślny próg
        $this->postJson('/api/alerts', [
            'city_id' => 759734, 'type' => 'rain_forecast_tomorrow', 'channels' => ['push'], 'cooldown_minutes' => 720,
        ])->assertCreated()
            ->assertJsonPath('data.threshold', null)
            ->assertJsonPath('data.effective_threshold', 50)
            ->assertJsonPath('data.forecast', true)
            ->assertJsonPath('data.cooldown_minutes', 720);

        $this->postJson('/api/alerts', [
            'city_id' => 759734, 'type' => 'frost_forecast', 'channels' => ['mail'],
        ])->assertCreated()->assertJsonPath('data.effective_threshold', 0);
    }

    public function test_validation_errors(): void
    {
        [$user] = $this->userWithFavorite();
        Sanctum::actingAs($user);

        $this->postJson('/api/alerts', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['city_id', 'type', 'channels']);

        $base = ['city_id' => 759734, 'channels' => ['mail']];

        $this->postJson('/api/alerts', $base + ['type' => 'unknown'])->assertJsonValidationErrors('type');
        $this->postJson('/api/alerts', $base + ['type' => 'temp_below'])
            ->assertJsonValidationErrors(['threshold' => 'Podaj próg alertu.']);
        $this->postJson('/api/alerts', $base + ['type' => 'temp_above', 'threshold' => 99])->assertJsonValidationErrors('threshold');
        $this->postJson('/api/alerts', $base + ['type' => 'humidity_above', 'threshold' => 'abc'])->assertJsonValidationErrors('threshold');
        $this->postJson('/api/alerts', $base + ['type' => 'pressure_below', 'threshold' => 500])->assertJsonValidationErrors('threshold');
        $this->postJson('/api/alerts', $base + ['type' => 'condition'])->assertJsonValidationErrors('condition');
        $this->postJson('/api/alerts', $base + ['type' => 'condition', 'condition' => 'hail'])->assertJsonValidationErrors('condition');
        $this->postJson('/api/alerts', ['city_id' => 759734, 'type' => 'wind_above', 'threshold' => 10, 'channels' => []])
            ->assertJsonValidationErrors('channels');
        $this->postJson('/api/alerts', ['city_id' => 759734, 'type' => 'wind_above', 'threshold' => 10, 'channels' => ['sms']])
            ->assertJsonValidationErrors('channels.0');
        $this->postJson('/api/alerts', $base + ['type' => 'wind_above', 'threshold' => 10, 'cooldown_minutes' => 5])
            ->assertJsonValidationErrors('cooldown_minutes');
        $this->postJson('/api/alerts', $base + ['type' => 'wind_above', 'threshold' => 10, 'label' => str_repeat('a', 101)])
            ->assertJsonValidationErrors('label');

        $this->assertSame(0, AlertRule::count());
    }

    public function test_city_must_be_in_favorites(): void
    {
        [$user] = $this->userWithFavorite();
        $this->makeCity(756135); // istnieje w bazie, ale nie w ulubionych
        Sanctum::actingAs($user);

        foreach ([756135, 999999] as $cityId) {
            $this->postJson('/api/alerts', [
                'city_id' => $cityId, 'type' => 'temp_below', 'threshold' => 0, 'channels' => ['mail'],
            ])->assertStatus(422)
                ->assertJsonValidationErrors(['city_id' => 'Alerty można ustawić tylko dla miast z ulubionych.']);
        }
    }

    public function test_rules_limit(): void
    {
        config(['alerts.rules_limit' => 2]);
        [$user, $city] = $this->userWithFavorite();
        $this->makeRule($user, $city);
        $this->makeRule($user, $city);
        Sanctum::actingAs($user);

        $this->postJson('/api/alerts', [
            'city_id' => 759734, 'type' => 'temp_below', 'threshold' => 0, 'channels' => ['mail'],
        ])->assertStatus(400)->assertJsonPath('error', 'Osiągnięto limit 2 reguł alertów');
    }

    public function test_index_lists_only_own_rules(): void
    {
        [$user, $city] = $this->userWithFavorite();
        [$other, $otherCity] = $this->userWithFavorite(756135);
        $this->makeRule($user, $city, ['type' => 'wind_above', 'threshold' => 15]);
        $this->makeRule($other, $otherCity);
        Sanctum::actingAs($user);

        $this->getJson('/api/alerts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'wind_above')
            ->assertJsonPath('data.0.city.id', 759734)
            ->assertJsonPath('limit', 20);
    }

    public function test_update_toggle_and_delete(): void
    {
        [$user, $city] = $this->userWithFavorite();
        $rule = $this->makeRule($user, $city, ['last_triggered_at' => now()]);
        Sanctum::actingAs($user);

        // Samo przełączenie aktywności nie zeruje cooldownu
        $this->putJson("/api/alerts/{$rule->id}", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.threshold', 0);
        $this->assertNotNull($rule->fresh()->last_triggered_at);

        // Zmiana warunku zeruje cooldown
        $this->patchJson("/api/alerts/{$rule->id}", ['type' => 'temp_above', 'threshold' => 30, 'channels' => ['push']])
            ->assertOk()
            ->assertJsonPath('data.type', 'temp_above')
            ->assertJsonPath('data.threshold', 30)
            ->assertJsonPath('data.channels', ['push']);
        $this->assertNull($rule->fresh()->last_triggered_at);

        // Zmiana typu na zjawisko wymaga podania zjawiska
        $this->putJson("/api/alerts/{$rule->id}", ['type' => 'condition'])->assertJsonValidationErrors('condition');
        $this->putJson("/api/alerts/{$rule->id}", ['threshold' => 100])->assertJsonValidationErrors('threshold');

        $this->deleteJson("/api/alerts/{$rule->id}")->assertOk();
        $this->assertSame(0, AlertRule::count());
    }

    public function test_cannot_modify_rules_of_other_users(): void
    {
        [$owner, $city] = $this->userWithFavorite();
        $rule = $this->makeRule($owner, $city);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/alerts/{$rule->id}", ['active' => false])
            ->assertNotFound()
            ->assertJsonPath('error', 'Nie znaleziono reguły alertu');
        $this->deleteJson("/api/alerts/{$rule->id}")->assertNotFound();

        $this->assertTrue($rule->fresh()->active);
    }

    public function test_meta_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/alerts/meta')
            ->assertOk()
            ->assertJsonCount(8, 'types')
            ->assertJsonPath('types.0.value', 'temp_below')
            ->assertJsonPath('conditions.0.value', 'rain')
            ->assertJsonPath('channels', ['mail', 'push'])
            ->assertJsonPath('cooldown.default', 360);
    }

    public function test_rules_are_deleted_with_user(): void
    {
        [$user, $city] = $this->userWithFavorite();
        $this->makeRule($user, $city);

        $user->delete();

        $this->assertSame(0, AlertRule::count());
    }
}
