<?php

namespace Tests\Feature;

use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesOpenWeather;
use Tests\Fixtures\OpenWeatherFixtures;
use Tests\TestCase;

class OutgoingRateLimitTest extends TestCase
{
    use RefreshDatabase, FakesOpenWeather;

    private const LIMIT_MESSAGE = 'Przekroczono limit zapytań do serwisu pogodowego. Spróbuj za chwilę.';

    public function test_requests_over_the_budget_are_not_sent(): void
    {
        config(['weather.rate_limit_per_minute' => 2]);
        $this->fakeOpenWeather();

        $this->getJson('/api/weather/759734')->assertOk();
        $this->getJson('/api/weather/756135')->assertOk();

        $this->getJson('/api/weather/3094802')
            ->assertStatus(503)
            ->assertExactJson(['error' => self::LIMIT_MESSAGE]);

        Http::assertSentCount(2);

        // Odpowiedzi z cache nie zużywają limitu
        $this->getJson('/api/weather/759734')->assertOk();
        Http::assertSentCount(2);

        // Po minucie limit się odnawia
        $this->travel(61)->seconds();
        $this->getJson('/api/weather/3094802')->assertOk();
        Http::assertSentCount(3);
    }

    public function test_pool_requests_count_individually(): void
    {
        config(['weather.rate_limit_per_minute' => 3]);
        $this->fakeOpenWeather();

        // 4 wpisy, limit 3: każdy wpis puli liczy się osobno, ostatni nie zostaje wysłany
        $coords = array_map(fn ($id) => ['lat' => OpenWeatherFixtures::CITIES[$id][1], 'lon' => OpenWeatherFixtures::CITIES[$id][2]],
            [759734, 756135, 3094802, 765876]);

        $ids = app(WeatherService::class)->cityIdsForCoordinates($coords);

        $this->assertSame([759734, 756135, 3094802, false], $ids);
        Http::assertSentCount(3);
    }

    public function test_upstream_429_is_retried_at_most_once_and_returns_503(): void
    {
        config(['weather.retry_times' => 3]);
        $this->app->forgetInstance(\App\Services\OpenWeatherClient::class);
        $this->app->forgetInstance(WeatherService::class);

        $this->fakeOpenWeather([
            '/data/2.5/weather' => Http::response(['cod' => 429, 'message' => 'too many'], 429),
        ]);

        $this->getJson('/api/weather/759734')
            ->assertStatus(503)
            ->assertExactJson(['error' => self::LIMIT_MESSAGE]);

        Http::assertSentCount(2);
    }

    public function test_zero_disables_the_limit(): void
    {
        config(['weather.rate_limit_per_minute' => 0]);
        $this->fakeOpenWeather();

        foreach ([759734, 756135, 3094802, 765876] as $id) {
            $this->getJson("/api/weather/{$id}")->assertOk();
        }

        Http::assertSentCount(4);
    }
}
