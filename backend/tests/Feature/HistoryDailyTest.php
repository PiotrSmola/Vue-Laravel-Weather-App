<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use App\Models\WeatherData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HistoryDailyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-31 12:00:00', 'UTC'));

        $this->city = City::factory()->create(['openweather_id' => 759734, 'name' => 'Rzeszów', 'country' => 'PL']);
        $this->user = User::factory()->create();
        $this->user->cities()->attach($this->city->id);
        Sanctum::actingAs($this->user);
    }

    private function measurement(string $at, array $attributes = []): WeatherData
    {
        return WeatherData::factory()->for($this->city)->at(Carbon::parse($at, 'UTC'))->create($attributes);
    }

    public function test_daily_aggregates_are_correct(): void
    {
        $this->measurement('2026-10-29 06:00:00', ['temperature' => 2.0, 'feels_like' => 0.0, 'humidity' => 90, 'pressure' => 1010, 'wind_speed' => 2.0, 'weather_condition' => 'Clouds', 'weather_icon' => '04n', 'weather_description' => 'pochmurnie']);
        $this->measurement('2026-10-29 12:00:00', ['temperature' => 10.0, 'feels_like' => 9.0, 'humidity' => 60, 'pressure' => 1012, 'wind_speed' => 6.0, 'weather_condition' => 'Clouds', 'weather_icon' => '04d', 'weather_description' => 'pochmurnie']);
        $this->measurement('2026-10-29 18:00:00', ['temperature' => 6.0, 'feels_like' => 3.0, 'humidity' => 75, 'pressure' => 1014, 'wind_speed' => 4.0, 'weather_condition' => 'Rain', 'weather_icon' => '10n', 'weather_description' => 'deszcz']);
        $this->measurement('2026-10-30 12:00:00', ['temperature' => 15.5, 'wind_speed' => 1.0, 'weather_condition' => 'Clear', 'weather_icon' => '01d']);

        $response = $this->getJson('/api/cities/759734/history/daily?from=2026-10-29&to=2026-10-30')
            ->assertOk()
            ->assertJsonPath('city.name', 'Rzeszów')
            ->assertJsonPath('city.openweather_id', 759734)
            ->assertJsonPath('range.tz', 'UTC')
            ->assertJsonCount(2, 'days')
            ->assertJsonStructure(['city', 'range' => ['from', 'to', 'tz'], 'days' => [[
                'date', 'count', 'temp_min', 'temp_max', 'temp_avg', 'feels_like_avg', 'humidity_avg',
                'pressure_avg', 'wind_avg', 'wind_max', 'condition', 'description', 'icon',
            ]]]);

        $day = $response->json('days.0');
        $this->assertSame('2026-10-29', $day['date']);
        $this->assertSame(3, $day['count']);
        $this->assertEquals(2.0, $day['temp_min']);
        $this->assertEquals(10.0, $day['temp_max']);
        $this->assertEquals(6.0, $day['temp_avg']);
        $this->assertEquals(4.0, $day['feels_like_avg']);
        $this->assertEquals(75.0, $day['humidity_avg']);
        $this->assertEquals(1012.0, $day['pressure_avg']);
        $this->assertEquals(4.0, $day['wind_avg']);
        $this->assertEquals(6.0, $day['wind_max']);
        $this->assertSame('Clouds', $day['condition']);
        $this->assertSame('04d', $day['icon']); // wariant dzienny ikony
        $this->assertSame('pochmurnie', $day['description']);

        $this->assertSame('2026-10-30', $response->json('days.1.date'));
        $this->assertSame(1, $response->json('days.1.count'));
        $this->assertSame('Clear', $response->json('days.1.condition'));

        Http::assertNothingSent();
    }

    public function test_days_are_grouped_in_requested_timezone(): void
    {
        // 23:30 UTC 29.10 = 00:30 30.10 w Warszawie (CET, UTC+1)
        $this->measurement('2026-10-29 23:30:00', ['temperature' => 1.0]);
        $this->measurement('2026-10-29 12:00:00', ['temperature' => 9.0]);

        $this->getJson('/api/cities/759734/history/daily?from=2026-10-29&to=2026-10-30')
            ->assertOk()
            ->assertJsonCount(1, 'days')
            ->assertJsonPath('days.0.count', 2);

        $this->getJson('/api/cities/759734/history/daily?from=2026-10-29&to=2026-10-30&tz=Europe/Warsaw')
            ->assertOk()
            ->assertJsonPath('range.tz', 'Europe/Warsaw')
            ->assertJsonCount(2, 'days')
            ->assertJsonPath('days.0.date', '2026-10-29')
            ->assertJsonPath('days.1.date', '2026-10-30');

        $this->getJson('/api/cities/759734/history/daily?tz=Mars/Olympus')
            ->assertStatus(422)
            ->assertJsonValidationErrors('tz');
    }

    public function test_default_range_and_empty_result(): void
    {
        $this->measurement('2026-10-20 12:00:00');
        $this->measurement('2026-10-30 12:00:00');

        // Domyślnie ostatnie 7 dni
        $this->getJson('/api/cities/759734/history/daily')->assertOk()->assertJsonCount(1, 'days');

        $this->getJson('/api/cities/759734/history/daily?from=2026-01-01&to=2026-01-31')
            ->assertOk()
            ->assertJsonCount(0, 'days');
    }

    public function test_range_validation_matches_historical(): void
    {
        $this->getJson('/api/cities/759734/history/daily?from=nie-data')->assertStatus(422)->assertJsonValidationErrors('from');
        $this->getJson('/api/cities/759734/history/daily?from=2026-10-30&to=2026-10-01')->assertStatus(422);
        $this->getJson('/api/cities/759734/history/daily?from=2024-01-01&to=2026-10-31')
            ->assertStatus(422)
            ->assertJsonValidationErrors('from');
    }

    public function test_requires_auth_and_favorite(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/cities/759734/history/daily')
            ->assertForbidden()
            ->assertJsonPath('error', 'Brak dostępu do danych historycznych dla tego miasta');
        $this->getJson('/api/cities/1/history/daily')->assertNotFound()->assertJsonPath('error', 'Nie znaleziono miasta');
    }

    public function test_guest_gets_401(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/cities/759734/history/daily')->assertUnauthorized();
        $this->getJson('/api/cities/759734/history/summary')->assertUnauthorized();
        $this->getJson('/api/cities/759734/history/export')->assertUnauthorized();
    }
}
