<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use App\Models\WeatherData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HistorySummaryTest extends TestCase
{
    use RefreshDatabase;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-31 12:00:00', 'UTC'));

        $this->city = City::factory()->create(['openweather_id' => 759734, 'name' => 'Rzeszów']);
        $user = User::factory()->create();
        $user->cities()->attach($this->city->id);
        Sanctum::actingAs($user);
    }

    private function measurement(string $at, array $attributes = []): WeatherData
    {
        return WeatherData::factory()->for($this->city)->at(Carbon::parse($at, 'UTC'))->create($attributes + [
            'temperature' => 10.0, 'feels_like' => 9.0, 'humidity' => 70, 'pressure' => 1013, 'wind_speed' => 3.0,
        ]);
    }

    public function test_records_averages_and_comparisons(): void
    {
        // Tydzień wcześniej (24.10) - średnia 8°C, w tym pomiar "tydzień temu" o 11:00
        foreach (['2026-10-24 11:00:00', '2026-10-25 11:00:00', '2026-10-26 11:00:00', '2026-10-27 11:00:00'] as $at) {
            $this->measurement($at, ['temperature' => 8.0, 'humidity' => 80]);
        }
        $this->measurement('2026-10-28 03:00:00', ['temperature' => -2.5, 'pressure' => 990]);
        $this->measurement('2026-10-29 14:00:00', ['temperature' => 21.0, 'pressure' => 1030, 'humidity' => 99, 'wind_speed' => 14.2]);
        // Wczoraj o 11:20 (w tolerancji ±90 min od 11:00)
        $this->measurement('2026-10-30 11:20:00', ['temperature' => 10.7, 'humidity' => 60]);
        // Dziś
        $this->measurement('2026-10-31 08:00:00', ['temperature' => 11.0]);
        $this->measurement('2026-10-31 11:00:00', ['temperature' => 13.0, 'humidity' => 65, 'pressure' => 1011, 'wind_speed' => 5.0]);

        $response = $this->getJson('/api/cities/759734/history/summary?from=2026-10-25&to=2026-10-31')
            ->assertOk()
            ->assertJsonPath('count', 8)
            ->assertJsonPath('records.temp_max.value', 21)
            ->assertJsonPath('records.temp_max.date', '2026-10-29 14:00:00')
            ->assertJsonPath('records.temp_max.timestamp', Carbon::parse('2026-10-29 14:00:00', 'UTC')->timestamp * 1000)
            ->assertJsonPath('records.temp_min.value', -2.5)
            ->assertJsonPath('records.temp_min.date', '2026-10-28 03:00:00')
            ->assertJsonPath('records.wind_max.value', 14.2)
            ->assertJsonPath('records.pressure_max.value', 1030)
            ->assertJsonPath('records.pressure_min.value', 990)
            ->assertJsonPath('records.humidity_max.value', 99)
            ->assertJsonPath('records.humidity_min.value', 60)
            ->assertJsonPath('first.date', '2026-10-25 11:00:00')
            ->assertJsonPath('last.date', '2026-10-31 11:00:00');

        // Średnia temperatur w zakresie: (8*3 - 2.5 + 21 + 10.7 + 11 + 13) / 8
        $this->assertEqualsWithDelta(round((24 - 2.5 + 21 + 10.7 + 11 + 13) / 8, 1), $response->json('averages.temperature'), 0.001);

        $comparisons = $response->json('comparisons');
        $this->assertSame('2026-10-31 11:00:00', $comparisons['latest']['date']);
        $this->assertEquals(13.0, $comparisons['latest']['temperature']);

        $this->assertSame('2026-10-30 11:20:00', $comparisons['vs_yesterday']['date']);
        $this->assertEqualsWithDelta(2.3, $comparisons['vs_yesterday']['delta']['temperature'], 0.001);
        $this->assertSame(5.0, (float) $comparisons['vs_yesterday']['delta']['humidity']);

        $this->assertSame('2026-10-24 11:00:00', $comparisons['vs_week_ago']['date']);
        $this->assertEqualsWithDelta(5.0, $comparisons['vs_week_ago']['delta']['temperature'], 0.001);

        // Dziś: (11 + 13) / 2 = 12; ostatnie 7 dni (24.10 00:00 - 30.10 23:59): 8,8,8,8,-2.5,21,10.7
        $weekAvg = round((32 - 2.5 + 21 + 10.7) / 7, 1);
        $this->assertEqualsWithDelta(12.0, $comparisons['today_vs_week']['today']['temperature'], 0.001);
        $this->assertSame(2, $comparisons['today_vs_week']['today']['count']);
        $this->assertSame(7, $comparisons['today_vs_week']['week']['count']);
        $this->assertEqualsWithDelta($weekAvg, $comparisons['today_vs_week']['week']['temperature'], 0.001);
        $this->assertEqualsWithDelta(round(12.0 - $weekAvg, 1), $comparisons['today_vs_week']['delta']['temperature'], 0.001);
    }

    public function test_comparison_is_null_without_matching_measurement(): void
    {
        $this->measurement('2026-10-31 11:00:00');
        // Wczoraj, ale poza tolerancją ±90 minut
        $this->measurement('2026-10-30 07:00:00');

        $this->getJson('/api/cities/759734/history/summary')
            ->assertOk()
            ->assertJsonPath('comparisons.vs_yesterday', null)
            ->assertJsonPath('comparisons.vs_week_ago', null)
            ->assertJsonPath('comparisons.today_vs_week.today.count', 1)
            ->assertJsonPath('comparisons.today_vs_week.week.count', 1);
    }

    public function test_no_data_is_handled_gracefully(): void
    {
        $this->getJson('/api/cities/759734/history/summary')
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('records', null)
            ->assertJsonPath('averages', null)
            ->assertJsonPath('first', null)
            ->assertJsonPath('comparisons.latest', null)
            ->assertJsonPath('comparisons.vs_yesterday', null)
            ->assertJsonPath('comparisons.today_vs_week', null);
    }

    public function test_requires_favorite_and_valid_range(): void
    {
        $this->getJson('/api/cities/759734/history/summary?from=2026-10-30&to=2026-10-01')->assertStatus(422);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/cities/759734/history/summary')->assertForbidden();
        $this->getJson('/api/cities/1/history/summary')->assertNotFound();
    }
}
