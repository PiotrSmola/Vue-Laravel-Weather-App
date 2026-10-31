<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\FakesOpenWeather;
use Tests\Fixtures\OpenWeatherFixtures;
use Tests\TestCase;

class HistoricalDataTest extends TestCase
{
    use RefreshDatabase, FakesOpenWeather;

    private User $user;
    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-31 12:00:00', 'UTC'));

        $service = app(WeatherService::class);
        $this->city = $service->cityFromPayload(OpenWeatherFixtures::current(759734));

        // Pomiary: dziś, 3 dni temu, 10 dni temu, 400 dni temu
        foreach ([0, 3, 10, 400] as $daysAgo) {
            $service->storeMeasurement($this->city, OpenWeatherFixtures::current(759734, [
                'dt' => now()->subDays($daysAgo)->subHour()->timestamp,
            ]));
        }

        $this->user = User::factory()->create();
        $this->user->cities()->attach($this->city->id);
        Sanctum::actingAs($this->user);
    }

    public function test_default_range_is_last_7_days_with_full_shape(): void
    {
        $this->getJson('/api/cities/759734/historical')
            ->assertOk()
            ->assertJsonPath('city', ['id' => $this->city->id, 'name' => 'Rzeszów', 'country' => 'PL'])
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['city' => ['id', 'name', 'country'], 'data' => [[
                'timestamp', 'date', 'temperature', 'humidity', 'pressure', 'wind_speed',
                'weather_condition', 'weather_icon',
                'feels_like', 'wind_direction', 'clouds', 'visibility', 'weather_description',
            ]]])
            ->assertJsonPath('data.0.date', '2026-10-28 11:00:00')
            ->assertJsonPath('data.0.timestamp', Carbon::parse('2026-10-28 11:00:00', 'UTC')->timestamp * 1000)
            ->assertJsonPath('data.1.feels_like', 17.9)
            ->assertJsonPath('data.1.wind_direction', 240)
            ->assertJsonPath('data.1.weather_description', 'bezchmurnie');
    }

    public function test_custom_range(): void
    {
        $this->getJson('/api/cities/759734/historical?from=2026-10-15&to=2026-10-31')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/cities/759734/historical?from=2026-10-21T00:00:00Z&to=2026-10-21T23:59:59Z')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Sam "from": do teraz
        $this->getJson('/api/cities/759734/historical?from=2025-11-01')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_range_validation(): void
    {
        $this->getJson('/api/cities/759734/historical?from=nie-data')->assertStatus(422)->assertJsonValidationErrors('from');
        $this->getJson('/api/cities/759734/historical?from=2026-10-30&to=2026-10-01')->assertStatus(422);
        $this->getJson('/api/cities/759734/historical?from=2024-01-01&to=2026-10-31')
            ->assertStatus(422)
            ->assertJsonValidationErrors('from');
    }

    public function test_history_requires_favorite(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/cities/759734/historical')->assertForbidden();
        $this->getJson('/api/cities/1/historical')->assertNotFound();
    }
}
