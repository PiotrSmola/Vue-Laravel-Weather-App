<?php

namespace Tests\Feature;

use App\Events\AirQualityUpdated;
use App\Models\AirQualityData;
use App\Models\City;
use App\Models\User;
use App\Services\AirQualityService;
use App\Services\WeatherService;
use App\Support\AirQualityIndex;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\FakesOpenWeather;
use Tests\Fixtures\OpenWeatherFixtures;
use Tests\TestCase;

class AirQualityTest extends TestCase
{
    use RefreshDatabase, FakesOpenWeather;

    // Domyślne odpowiedzi Air Pollution API + ewentualne własne nadpisania
    private function fakeAir(array $overrides = []): void
    {
        $this->fakeOpenWeather($overrides + [
            '/data/2.5/air_pollution/forecast' => fn (Request $r, array $q) => Http::response(
                OpenWeatherFixtures::airPollutionForecast((float) $q['lat'], (float) $q['lon'])
            ),
            '/data/2.5/air_pollution' => fn (Request $r, array $q) => Http::response(
                OpenWeatherFixtures::airPollution((float) $q['lat'], (float) $q['lon'])
            ),
        ]);
    }

    private function airRequests(string $path = '/data/2.5/air_pollution'): int
    {
        return count(Http::recorded(fn (Request $r) => parse_url($r->url(), PHP_URL_PATH) === $path));
    }

    public function test_current_air_quality_for_city(): void
    {
        $this->fakeAir();

        $this->getJson('/api/air/759734')
            ->assertOk()
            ->assertJsonPath('city', ['id' => 759734, 'name' => 'Rzeszów', 'country' => 'PL'])
            ->assertJsonPath('aqi', 2)
            ->assertJsonPath('label', 'Dostateczna')
            ->assertJsonPath('components.pm2_5', 18.2)
            ->assertJsonPath('components.pm10', 24.7)
            ->assertJsonPath('components.nh3', 1.2)
            ->assertJsonPath('dominant_pollutant.key', 'pm2_5')
            ->assertJsonPath('dominant_pollutant.name', 'PM2.5')
            ->assertJsonPath('dominant_pollutant.unit', 'µg/m³')
            ->assertJsonPath('indexes.european', ['level' => 2, 'label' => 'Dostateczna', 'pollutant' => 'pm2_5'])
            ->assertJsonPath('indexes.polish', ['level' => 2, 'label' => 'Dobry', 'pollutant' => 'pm2_5'])
            ->assertJsonPath('timestamp', 1730383200 * 1000)
            ->assertJsonStructure(['coord' => ['lat', 'lon'], 'date', 'components' => AirQualityData::COMPONENTS]);

        // Zapytanie z współrzędnymi miasta (klucz API dodany)
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/data/2.5/air_pollution?')
            && $r['lat'] == 50.0413 && $r['lon'] == 21.999 && $r['appid'] === 'test-key');
    }

    public function test_current_air_quality_is_cached_and_stored(): void
    {
        Event::fake([AirQualityUpdated::class]);
        $this->fakeAir();

        $this->getJson('/api/air/759734')->assertOk();
        $this->getJson('/api/air/759734')->assertOk();

        $this->assertSame(1, $this->airRequests());
        $this->assertSame(1, AirQualityData::count());

        $row = AirQualityData::first();
        $this->assertSame(2, $row->aqi);
        $this->assertSame(18.2, $row->pm2_5);
        $this->assertSame('2024-10-31 14:00:00', $row->measured_at->format('Y-m-d H:i:s'));
        $this->assertSame(City::where('openweather_id', 759734)->value('id'), $row->city_id);

        Event::assertDispatchedTimes(AirQualityUpdated::class, 1);
    }

    public function test_unknown_city_returns_404(): void
    {
        $this->fakeAir([
            '/data/2.5/weather' => Http::response(OpenWeatherFixtures::error(), 404),
        ]);

        $this->getJson('/api/air/123')->assertNotFound()->assertJsonPath('error', 'Nie znaleziono miasta');
        $this->getJson('/api/air/abc')->assertNotFound();
    }

    public function test_api_error_returns_502_without_leaking_details(): void
    {
        $this->fakeAir([
            '/data/2.5/air_pollution' => Http::response(['message' => 'secret upstream info'], 500),
        ]);

        $this->getJson('/api/air/759734')
            ->assertStatus(502)
            ->assertExactJson(['error' => 'Nie udało się pobrać danych o jakości powietrza']);
    }

    public function test_empty_api_response_returns_404(): void
    {
        $this->fakeAir([
            '/data/2.5/air_pollution' => Http::response(['coord' => ['lat' => 50, 'lon' => 21], 'list' => []]),
        ]);

        $this->getJson('/api/air/759734')
            ->assertNotFound()
            ->assertJsonPath('error', 'Brak danych o jakości powietrza dla tej lokalizacji');
        $this->assertSame(0, AirQualityData::count());
    }

    public function test_air_quality_by_coordinates(): void
    {
        $this->fakeAir();

        $this->getJson('/api/air/coordinates?lat=50.06&lon=19.94')
            ->assertOk()
            ->assertJsonPath('city', null)
            ->assertJsonPath('coord', ['lat' => 50.06, 'lon' => 19.94])
            ->assertJsonPath('aqi', 2);

        // Bez zapisu w historii i bez tworzenia miasta
        $this->assertSame(0, AirQualityData::count());
        $this->assertSame(0, City::count());

        $this->getJson('/api/air/coordinates?lat=50.06&lon=19.94')->assertOk();
        $this->assertSame(1, $this->airRequests());
    }

    public function test_coordinates_are_validated(): void
    {
        $this->fakeAir();

        $this->getJson('/api/air/coordinates')->assertStatus(422)->assertJsonValidationErrors(['lat', 'lon']);
        $this->getJson('/api/air/coordinates?lat=abc&lon=21')->assertStatus(422);
        $this->getJson('/api/air/coordinates?lat=50&lon=181')->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_forecast(): void
    {
        $this->fakeAir();

        $response = $this->getJson('/api/air/759734/forecast')
            ->assertOk()
            ->assertJsonPath('city.id', 759734)
            ->assertJsonCount(96, 'list')
            ->assertJsonPath('list.0', [
                'timestamp' => 1730386800 * 1000,
                'date' => '2024-10-31 15:00:00',
                'aqi' => 1,
                'label' => 'Dobra',
                'pm2_5' => 5,
                'pm10' => 10,
            ])
            ->assertJsonPath('list.3.aqi', 4)
            ->assertJsonPath('list.3.label', 'Zła');

        $this->getJson('/api/air/759734/forecast')->assertOk();
        $this->assertSame(1, $this->airRequests('/data/2.5/air_pollution/forecast'));
    }

    public function test_index_thresholds(): void
    {
        $this->assertSame(1, AirQualityIndex::level(9.99, AirQualityIndex::EUROPEAN_THRESHOLDS['pm2_5']));
        $this->assertSame(2, AirQualityIndex::level(10, AirQualityIndex::EUROPEAN_THRESHOLDS['pm2_5']));
        $this->assertSame(6, AirQualityIndex::level(500, AirQualityIndex::EUROPEAN_THRESHOLDS['pm2_5']));

        $european = AirQualityIndex::european(['pm2_5' => 30, 'pm10' => 160, 'o3' => 20]);
        $this->assertSame(['level' => 6, 'label' => 'Skrajnie zła', 'pollutant' => 'pm10'], $european);

        $polish = AirQualityIndex::polish(['pm2_5' => 60, 'pm10' => 30]);
        $this->assertSame(['level' => 4, 'label' => 'Dostateczny', 'pollutant' => 'pm2_5'], $polish);

        $this->assertNull(AirQualityIndex::european([]));
        $this->assertSame('pm10', AirQualityIndex::dominant(['pm2_5' => 12, 'pm10' => 160, 'o3' => 10])['key']);
        $this->assertSame(5, AirQualityIndex::dominant(['pm2_5' => 300])['level']);
    }

    public function test_history_for_favorite_city(): void
    {
        $this->travelTo(Carbon::parse('2026-10-31 12:00:00', 'UTC'));
        $city = app(WeatherService::class)->cityFromPayload(OpenWeatherFixtures::current(759734));
        $air = app(AirQualityService::class);

        foreach ([0, 3, 10, 400] as $daysAgo) {
            $air->storeMeasurement($city, OpenWeatherFixtures::airPollution(overrides: [
                'list' => [['dt' => now()->subDays($daysAgo)->subHour()->timestamp]],
            ])['list'][0]);
        }

        $user = User::factory()->create();
        $user->cities()->attach($city->id);
        Sanctum::actingAs($user);

        $this->getJson('/api/air/759734/historical')
            ->assertOk()
            ->assertJsonPath('city', ['id' => $city->id, 'name' => 'Rzeszów', 'country' => 'PL'])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.date', '2026-10-28 11:00:00')
            ->assertJsonPath('data.0.timestamp', Carbon::parse('2026-10-28 11:00:00', 'UTC')->timestamp * 1000)
            ->assertJsonPath('data.1.aqi', 2)
            ->assertJsonPath('data.1.pm2_5', 18.2)
            ->assertJsonStructure(['data' => [array_merge(['timestamp', 'date', 'aqi'], AirQualityData::COMPONENTS)]]);

        $this->getJson('/api/air/759734/historical?from=2026-10-15&to=2026-10-31')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/air/759734/historical?from=2025-11-01')->assertOk()->assertJsonCount(3, 'data');

        // Walidacja zakresu
        $this->getJson('/api/air/759734/historical?from=nie-data')->assertStatus(422)->assertJsonValidationErrors('from');
        $this->getJson('/api/air/759734/historical?from=2026-10-30&to=2026-10-01')->assertStatus(422);
        $this->getJson('/api/air/759734/historical?from=2024-01-01&to=2026-10-31')
            ->assertStatus(422)
            ->assertJsonValidationErrors('from');
    }

    public function test_history_requires_auth_and_favorite(): void
    {
        $city = app(WeatherService::class)->cityFromPayload(OpenWeatherFixtures::current(759734));

        $this->getJson('/api/air/759734/historical')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/air/759734/historical')->assertForbidden();
        $this->getJson('/api/air/1/historical')->assertNotFound();
        $this->assertNotNull($city);
    }

    public function test_update_command_stores_measurements_for_favorites_and_defaults(): void
    {
        $this->fakeAir();
        $user = User::factory()->create();
        $paris = City::create(['openweather_id' => 2988507, 'name' => 'Paris', 'country' => 'FR', 'latitude' => 48.85, 'longitude' => 2.35]);
        $user->cities()->attach($paris->id);

        $this->artisan('air:update')
            ->expectsOutput('Sukces: 11 miast, Błędy: 0 miast')
            ->assertSuccessful();

        $this->assertSame(11, City::count());
        $this->assertSame(11, AirQualityData::count());
        $this->assertSame(1, AirQualityData::where('city_id', $paris->id)->count());
        $this->assertSame(11, $this->airRequests());

        // Ponowne uruchomienie: zawsze świeże dane z API, ale bez duplikatów (ten sam czas pomiaru)
        $this->artisan('air:update')->assertSuccessful();
        $this->assertSame(11, AirQualityData::count());
        $this->assertSame(22, $this->airRequests());

        // Odświeżony cache jest używany przez endpoint
        $this->getJson('/api/air/2988507')->assertOk()->assertJsonPath('city.name', 'Paris');
        $this->assertSame(22, $this->airRequests());
    }

    public function test_update_command_counts_failures(): void
    {
        $this->fakeAir([
            '/data/2.5/air_pollution' => fn (Request $r, array $q) => (float) $q['lat'] === 52.2298
                ? Http::response(OpenWeatherFixtures::error(500, 'error'), 500)
                : Http::response(OpenWeatherFixtures::airPollution((float) $q['lat'], (float) $q['lon'])),
        ]);

        $this->artisan('air:update')
            ->expectsOutput('Sukces: 9 miast, Błędy: 1 miast')
            ->assertSuccessful();

        $this->assertSame(9, AirQualityData::count());
    }

    public function test_command_is_scheduled_hourly(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'air:update'));

        $this->assertCount(1, $events);
        // 15. minuta - nie nakłada się na weather:update (co 30 min od pełnej godziny)
        $this->assertSame('15 * * * *', $events->first()->expression);
    }
}
