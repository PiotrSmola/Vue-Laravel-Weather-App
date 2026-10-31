<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\WeatherData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesOpenWeather;
use Tests\Fixtures\OpenWeatherFixtures;
use Tests\TestCase;

class WeatherEndpointsTest extends TestCase
{
    use RefreshDatabase, FakesOpenWeather;

    public function test_coordinates_route_is_not_shadowed_by_city_id_route(): void
    {
        $this->fakeOpenWeather();

        $this->getJson('/api/weather/coordinates?lat=50&lon=21')
            ->assertOk()
            ->assertJsonPath('id', 759734)
            ->assertJsonPath('name', 'Rzeszów');

        $this->getJson('/api/weather/forecast/coordinates?lat=50&lon=21')
            ->assertOk()
            ->assertJsonPath('city.id', 759734);
    }

    public function test_city_query_routes(): void
    {
        $this->fakeOpenWeather();

        $this->getJson('/api/weather/city?q=Kraków')->assertOk()->assertJsonPath('id', 3094802);
        $this->getJson('/api/weather/forecast/city?q=Kraków')->assertOk()->assertJsonPath('city.id', 3094802);
        $this->getJson('/api/weather/city')->assertStatus(422);
    }

    public function test_coordinates_are_validated(): void
    {
        $this->fakeOpenWeather();

        $this->getJson('/api/weather/coordinates?lat=abc&lon=21')->assertStatus(422);
        $this->getJson('/api/weather/coordinates?lat=95&lon=21')->assertStatus(422);
    }

    public function test_non_numeric_city_id_returns_404(): void
    {
        $this->fakeOpenWeather();

        $this->getJson('/api/weather/abc')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_current_weather_creates_city_and_stores_measurement(): void
    {
        $this->fakeOpenWeather();

        $this->getJson('/api/weather/759734')
            ->assertOk()
            ->assertJsonPath('main.temp', 18.5)
            ->assertJsonPath('weather.0.description', 'bezchmurnie');

        $city = City::where('openweather_id', 759734)->firstOrFail();
        $this->assertSame('Rzeszów', $city->name);
        $this->assertSame(1, WeatherData::where('city_id', $city->id)->count());

        Http::assertSent(fn (Request $r) => $r['units'] === 'metric' && $r['lang'] === 'pl' && $r['appid'] === 'test-key');
    }

    public function test_current_weather_is_cached_per_lang(): void
    {
        $this->fakeOpenWeather();

        $this->getJson('/api/weather/759734')->assertOk();
        $this->getJson('/api/weather/759734')->assertOk();
        Http::assertSentCount(1);

        $this->getJson('/api/weather/759734?lang=en')->assertOk();
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['lang'] === 'en');
    }

    public function test_lang_is_validated(): void
    {
        $this->fakeOpenWeather();

        $this->getJson('/api/weather/759734?lang=de')->assertStatus(422);
        $this->getJson('/api/weather/forecast/759734?lang=de')->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_forecast_for_city(): void
    {
        $this->fakeOpenWeather();

        $this->getJson('/api/weather/forecast/3094802?lang=en')
            ->assertOk()
            ->assertJsonPath('city.id', 3094802)
            ->assertJsonCount(8, 'list');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/data/2.5/forecast') && $r['lang'] === 'en');
    }

    public function test_upstream_404_returns_generic_not_found(): void
    {
        $this->fakeOpenWeather([
            '/data/2.5/*' => Http::response(OpenWeatherFixtures::error(404, 'city not found'), 404),
        ]);

        $this->getJson('/api/weather/123')
            ->assertNotFound()
            ->assertExactJson(['error' => 'Nie znaleziono miasta']);
    }

    public function test_upstream_error_body_is_not_leaked(): void
    {
        $this->fakeOpenWeather([
            '/data/2.5/*' => Http::response(['cod' => 401, 'message' => 'Invalid API key SECRET'], 401),
        ]);

        $response = $this->getJson('/api/weather/759734')->assertStatus(502);

        $this->assertStringNotContainsString('SECRET', $response->getContent());
        $response->assertExactJson(['error' => 'Nie udało się pobrać danych pogodowych']);
    }

    public function test_server_errors_are_retried(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['message' => 'boom'], 500)
                ->push(OpenWeatherFixtures::current(759734)),
        ]);

        $this->getJson('/api/weather/759734')->assertOk()->assertJsonPath('id', 759734);
        Http::assertSentCount(2);
    }
}
