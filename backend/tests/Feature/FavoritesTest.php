<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use App\Models\WeatherData;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\FakesOpenWeather;
use Tests\Fixtures\OpenWeatherFixtures;
use Tests\TestCase;

class FavoritesTest extends TestCase
{
    use RefreshDatabase, FakesOpenWeather;

    public function test_add_city_to_favorites(): void
    {
        $this->fakeOpenWeather();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/cities', ['city_id' => 759734])
            ->assertOk()
            ->assertJsonPath('message', 'Miasto dodane do ulubionych')
            ->assertJsonPath('city.openweather_id', 759734)
            ->assertJsonPath('city.name', 'Rzeszów');

        $this->assertSame(1, $user->cities()->count());
        $this->assertNotNull(DB::table('user_cities')->first()->created_at);

        $this->postJson('/api/cities', ['city_id' => 759734])
            ->assertStatus(400)
            ->assertJsonPath('error', 'Miasto już jest w ulubionych');
    }

    public function test_add_unknown_city_returns_404(): void
    {
        $this->fakeOpenWeather(['/data/2.5/weather' => Http::response(OpenWeatherFixtures::error(), 404)]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/cities', ['city_id' => 1])
            ->assertNotFound()
            ->assertJsonPath('error', 'Nie znaleziono miasta');
    }

    public function test_favorites_limit_is_configurable(): void
    {
        config(['weather.favorites_limit' => 2]);
        $this->fakeOpenWeather();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/cities', ['city_id' => 759734])->assertOk();
        $this->postJson('/api/cities', ['city_id' => 756135])->assertOk();
        $this->postJson('/api/cities', ['city_id' => 3094802])
            ->assertStatus(400)
            ->assertJsonPath('error', 'Osiągnięto limit 2 ulubionych miast');
    }

    public function test_remove_city_keeps_city_and_history(): void
    {
        $this->fakeOpenWeather();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/cities', ['city_id' => 3085128])->assertOk(); // Szczecin
        $city = City::where('openweather_id', 3085128)->firstOrFail();
        $this->assertSame(1, $city->weatherData()->count());

        $this->deleteJson('/api/cities/3085128')
            ->assertOk()
            ->assertJsonPath('message', 'Miasto usunięte z ulubionych');

        $this->assertSame(0, $user->cities()->count());
        $this->assertNotNull(City::find($city->id));
        $this->assertSame(1, WeatherData::where('city_id', $city->id)->count());
    }

    public function test_remove_unknown_city_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/cities/999')->assertNotFound();
    }

    public function test_user_cities_include_latest_measurement(): void
    {
        $this->fakeOpenWeather();
        $user = User::factory()->create();
        $service = app(WeatherService::class);

        $city = $service->cityFromPayload(OpenWeatherFixtures::current(759734));
        $service->storeMeasurement($city, OpenWeatherFixtures::current(759734, ['dt' => 1730383200, 'main' => ['temp' => 10.0]]));
        $service->storeMeasurement($city, OpenWeatherFixtures::current(759734, ['dt' => 1730386800, 'main' => ['temp' => 12.5]]));
        $user->cities()->attach($city->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/cities/user')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.openweather_id', 759734)
            ->assertJsonPath('0.current_temp', 12.5)
            ->assertJsonPath('0.current_condition', 'Clear')
            ->assertJsonPath('0.current_icon', '01d');
    }

    public function test_favorites_require_authentication(): void
    {
        $this->getJson('/api/cities/user')->assertUnauthorized();
        $this->postJson('/api/cities', ['city_id' => 1])->assertUnauthorized();
        $this->deleteJson('/api/cities/1')->assertUnauthorized();
    }
}
