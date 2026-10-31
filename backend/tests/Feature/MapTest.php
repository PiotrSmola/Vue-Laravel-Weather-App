<?php

namespace Tests\Feature;

use App\Http\Controllers\MapController;
use App\Models\User;
use App\Services\OpenWeatherClient;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\FakesOpenWeather;
use Tests\Fixtures\OpenWeatherFixtures;
use Tests\TestCase;

class MapTest extends TestCase
{
    use RefreshDatabase, FakesOpenWeather;

    private const PNG = "\x89PNG\r\n\x1a\nfake-tile";

    protected function setUp(): void
    {
        parent::setUp();

        // Kafelki w cache w pamięci - testy nie zapisują plików
        config(['weather.map.tile_cache_store' => 'array']);
    }

    private function fakeTiles(int $status = 200): void
    {
        Http::fake([
            'tile.openweathermap.org/*' => Http::response($status === 200 ? self::PNG : '{"cod":401}', $status,
                ['Content-Type' => $status === 200 ? 'image/png' : 'application/json']),
        ]);
    }

    public function test_tile_is_proxied_with_server_side_key(): void
    {
        $this->fakeTiles();

        $response = $this->get('/api/map/tiles/temp_new/3/4/2.png')->assertOk();

        $this->assertSame(self::PNG, $response->getContent());
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age=3600', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://tile.openweathermap.org/map/temp_new/3/4/2.png')
            && $request['appid'] === 'test-key');
    }

    public function test_tile_is_cached(): void
    {
        $this->fakeTiles();

        $this->get('/api/map/tiles/clouds_new/2/1/1.png')->assertOk();
        $this->get('/api/map/tiles/clouds_new/2/1/1.png')->assertOk();
        $this->get('/api/map/tiles/clouds_new/2/1/2.png')->assertOk();

        Http::assertSentCount(2);
    }

    public function test_unknown_layer_is_rejected(): void
    {
        $this->fakeTiles();

        $this->getJson('/api/map/tiles/secret_layer/1/0/0.png')
            ->assertNotFound()
            ->assertJsonPath('error', 'Nieznana warstwa mapy');
        $this->getJson('/api/map/tiles/TEMP-NEW/1/0/0.png')->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_tile_coordinates_are_validated(): void
    {
        $this->fakeTiles();

        foreach (['11/0/0', '13/0/0', '2/4/0', '2/0/4', '0/1/0', '1/2/1'] as $coords) {
            $this->getJson("/api/map/tiles/wind_new/{$coords}.png")
                ->assertStatus(422)
                ->assertJsonPath('error', 'Nieprawidłowe współrzędne kafelka');
        }

        // Ujemne / nienumeryczne wartości nie pasują do trasy
        $this->getJson('/api/map/tiles/wind_new/-1/0/0.png')->assertNotFound();
        $this->getJson('/api/map/tiles/wind_new/a/0/0.png')->assertNotFound();

        $this->get('/api/map/tiles/wind_new/10/1023/1023.png')->assertOk();
        $this->get('/api/map/tiles/pressure_new/0/0/0.png')->assertOk();

        Http::assertSentCount(2);
    }

    public function test_upstream_error_returns_transparent_fallback_not_cached(): void
    {
        $this->fakeTiles(401);

        $response = $this->get('/api/map/tiles/precipitation_new/1/0/0.png')
            ->assertOk()
            ->assertHeader('X-Tile-Fallback', '1')
            ->assertHeader('Content-Type', 'image/png');
        $this->assertSame(MapController::transparentPng(), $response->getContent());
        $this->assertStringContainsString('max-age=60', $response->headers->get('Cache-Control'));

        $this->get('/api/map/tiles/precipitation_new/1/0/0.png')->assertHeader('X-Tile-Fallback', '1');

        Http::assertSentCount(2);
    }

    public function test_transparent_png_is_valid_256x256(): void
    {
        $png = MapController::transparentPng();

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $this->assertSame([256, 256], array_values(unpack('N2', substr($png, 16, 8))));
    }

    public function test_tile_budget_exhaustion_returns_fallback_without_upstream_calls(): void
    {
        config(['weather.tile_rate_limit_per_minute' => 2]);
        $this->fakeTiles();

        $this->get('/api/map/tiles/temp_new/1/0/0.png')->assertOk()->assertHeaderMissing('X-Tile-Fallback');
        $this->get('/api/map/tiles/temp_new/1/0/1.png')->assertOk()->assertHeaderMissing('X-Tile-Fallback');

        $fallback = $this->get('/api/map/tiles/temp_new/1/1/0.png')->assertOk()->assertHeader('X-Tile-Fallback', '1');
        $this->assertSame(MapController::transparentPng(), $fallback->getContent());

        // Kafelki z cache są nadal serwowane
        $cached = $this->get('/api/map/tiles/temp_new/1/0/0.png')->assertOk()->assertHeaderMissing('X-Tile-Fallback');
        $this->assertSame(self::PNG, $cached->getContent());

        Http::assertSentCount(2);

        // Limit kafelków nie zużywa limitu danych pogodowych
        $this->assertSame(0, RateLimiter::attempts(OpenWeatherClient::RATE_LIMIT_KEY));

        // Po minucie limit się odnawia
        $this->travel(61)->seconds();
        $this->get('/api/map/tiles/temp_new/1/1/0.png')->assertOk()->assertHeaderMissing('X-Tile-Fallback');
        Http::assertSentCount(3);
    }

    public function test_tiles_are_rate_limited(): void
    {
        config(['weather.map.tile_rate_limit' => 2]);
        $this->fakeTiles();

        $this->get('/api/map/tiles/temp_new/0/0/0.png')->assertOk();
        $this->get('/api/map/tiles/temp_new/0/0/0.png')->assertOk();
        $this->getJson('/api/map/tiles/temp_new/0/0/0.png')
            ->assertStatus(429)
            ->assertJsonPath('error', 'Zbyt wiele zapytań o kafelki mapy. Spróbuj ponownie za chwilę.');
    }

    public function test_map_cities_for_guest_use_stored_measurements_only(): void
    {
        $this->fakeOpenWeather();
        $service = app(WeatherService::class);

        foreach (array_keys(OpenWeatherFixtures::CITIES) as $id) {
            $service->cityFromPayload(OpenWeatherFixtures::current($id)); // bez pomiarów
        }
        $city = $service->cityFromPayload(OpenWeatherFixtures::current(756135));
        $service->storeMeasurement($city, OpenWeatherFixtures::current(756135, ['main' => ['temp' => 4.5]]));

        $this->getJson('/api/map/cities')
            ->assertOk()
            ->assertJsonCount(10)
            ->assertJsonPath('0.id', 756135)
            ->assertJsonPath('0.name', 'Warszawa')
            ->assertJsonPath('0.lat', 52.2298)
            ->assertJsonPath('0.temp', 4.5)
            ->assertJsonPath('0.icon', '01d')
            ->assertJsonPath('0.is_favorite', false)
            ->assertJsonPath('0.is_default', true)
            ->assertJsonPath('1.id', 3094802)
            ->assertJsonPath('1.temp', null);

        Http::assertNothingSent();
    }

    public function test_missing_default_cities_are_created_once(): void
    {
        $this->fakeOpenWeather();

        $this->getJson('/api/map/cities')->assertOk()->assertJsonCount(10)->assertJsonPath('0.temp', 18.5);
        $this->getJson('/api/map/cities')->assertOk()->assertJsonCount(10);

        Http::assertSentCount(10);
    }

    public function test_map_cities_include_favorites_first_with_alias(): void
    {
        $this->fakeOpenWeather();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/cities', ['city_id' => 2643743])->assertOk(); // spoza domyślnych
        $this->postJson('/api/cities', ['city_id' => 759734])->assertOk();  // domyślne
        $this->patchJson('/api/cities/user/759734', ['alias' => 'Dom'])->assertOk();

        $response = $this->getJson('/api/map/cities')->assertOk();
        $ids = array_column($response->json(), 'id');

        $this->assertSame([2643743, 759734], array_slice($ids, 0, 2));
        $this->assertCount(11, $ids);
        $this->assertSame(1, array_count_values($ids)[759734]);
        $response->assertJsonPath('1.alias', 'Dom')
            ->assertJsonPath('1.is_favorite', true)
            ->assertJsonPath('0.is_default', false)
            ->assertJsonPath('0.temp', 18.5);
    }
}
