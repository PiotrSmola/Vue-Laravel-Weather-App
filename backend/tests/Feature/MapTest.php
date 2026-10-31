<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
        $this->assertStringContainsString('max-age=1800', $response->headers->get('Cache-Control'));
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

        foreach (['13/0/0', '2/4/0', '2/0/4', '0/1/0', '1/2/1'] as $coords) {
            $this->getJson("/api/map/tiles/wind_new/{$coords}.png")
                ->assertStatus(422)
                ->assertJsonPath('error', 'Nieprawidłowe współrzędne kafelka');
        }

        // Ujemne / nienumeryczne wartości nie pasują do trasy
        $this->getJson('/api/map/tiles/wind_new/-1/0/0.png')->assertNotFound();
        $this->getJson('/api/map/tiles/wind_new/a/0/0.png')->assertNotFound();

        $this->get('/api/map/tiles/wind_new/12/4095/4095.png')->assertOk();
        $this->get('/api/map/tiles/pressure_new/0/0/0.png')->assertOk();

        Http::assertSentCount(2);
    }

    public function test_upstream_error_is_not_leaked_or_cached(): void
    {
        $this->fakeTiles(401);

        $this->getJson('/api/map/tiles/precipitation_new/1/0/0.png')
            ->assertStatus(502)
            ->assertExactJson(['error' => 'Nie udało się pobrać kafelka mapy']);
        $this->getJson('/api/map/tiles/precipitation_new/1/0/0.png')->assertStatus(502);

        Http::assertSentCount(2);
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
