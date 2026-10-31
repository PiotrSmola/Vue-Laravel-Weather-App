<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\FakesOpenWeather;
use Tests\Fixtures\OpenWeatherFixtures;
use Tests\TestCase;

class OneCallTest extends TestCase
{
    use RefreshDatabase, FakesOpenWeather;

    private function oneCallPayload(): array
    {
        $daily = [];
        for ($i = 0; $i < 9; $i++) {
            $daily[] = [
                'dt' => 1730372400 + $i * 86400,
                'summary' => 'Dzień ' . $i,
                'temp' => ['min' => 3 + $i, 'max' => 12 + $i, 'day' => 10, 'night' => 4],
                'pop' => 0.4,
                'rain' => 1.2,
                'uvi' => 2.5,
                'wind_speed' => 4.1,
                'weather' => [['main' => 'Rain', 'description' => 'lekki deszcz', 'icon' => '10d']],
            ];
        }

        return [
            'lat' => 50.0413,
            'lon' => 21.999,
            'timezone_offset' => 3600,
            'current' => ['uvi' => 3.7, 'temp' => 11.2],
            'minutely' => [['dt' => 1730383200, 'precipitation' => 0.5], ['dt' => 1730383260, 'precipitation' => 0]],
            'daily' => $daily,
            'alerts' => [[
                'sender_name' => 'IMGW-PIB',
                'event' => 'Silny wiatr',
                'start' => 1730383200,
                'end' => 1730426400,
                'description' => 'Prognozuje się silny wiatr.',
                'tags' => ['Wind'],
            ]],
        ];
    }

    public function test_features_reports_onecall_flag(): void
    {
        config(['weather.onecall_enabled' => false]);
        $this->getJson('/api/features')->assertOk()->assertExactJson(['onecall' => false]);

        config(['weather.onecall_enabled' => true]);
        $this->getJson('/api/features')->assertOk()->assertExactJson(['onecall' => true]);
    }

    public function test_disabled_returns_404_without_api_calls(): void
    {
        config(['weather.onecall_enabled' => false]);
        $this->fakeOpenWeather();

        $this->getJson('/api/weather/onecall/759734')
            ->assertNotFound()
            ->assertExactJson(['error' => 'Funkcja niedostępna']);

        Http::assertNothingSent();
    }

    public function test_enabled_returns_normalized_data_and_caches(): void
    {
        config(['weather.onecall_enabled' => true]);
        $this->fakeOpenWeather(['/data/3.0/onecall' => Http::response($this->oneCallPayload())]);

        $this->getJson('/api/weather/onecall/759734')
            ->assertOk()
            ->assertJsonPath('uvi', 3.7)
            ->assertJsonCount(8, 'daily')
            ->assertJsonPath('daily.0.temp_max', 12)
            ->assertJsonPath('daily.0.weather.icon', '10d')
            ->assertJsonPath('alerts.0.event', 'Silny wiatr')
            ->assertJsonPath('alerts.0.sender', 'IMGW-PIB')
            ->assertJsonCount(2, 'minutely')
            ->assertJsonPath('minutely.0.precipitation', 0.5);

        $this->getJson('/api/weather/onecall/759734')->assertOk();

        $oneCallRequests = Http::recorded(fn (Request $r) => Str::contains($r->url(), '/data/3.0/onecall'));
        $this->assertCount(1, $oneCallRequests);
        [$request] = $oneCallRequests->first();
        $this->assertSame('50.0413', (string) $request['lat']);
        $this->assertSame('metric', $request['units']);
    }

    public function test_unauthorized_upstream_returns_404_and_is_cached_for_an_hour(): void
    {
        config(['weather.onecall_enabled' => true]);
        $this->fakeOpenWeather([
            '/data/3.0/onecall' => Http::response(OpenWeatherFixtures::error(401, 'Invalid API key'), 401),
        ]);

        $this->getJson('/api/weather/onecall/759734')
            ->assertNotFound()
            ->assertExactJson(['error' => 'Funkcja niedostępna']);

        // Kolejne zapytania (także o inne miasta) nie trafiają do API
        $this->getJson('/api/weather/onecall/756135')->assertNotFound();
        $this->getJson('/api/features')->assertExactJson(['onecall' => false]);

        $this->assertCount(1, Http::recorded(fn (Request $r) => Str::contains($r->url(), '/data/3.0/onecall')));

        $this->travel(61)->minutes();
        $this->getJson('/api/features')->assertExactJson(['onecall' => true]);
    }

    public function test_other_upstream_errors_are_generic(): void
    {
        config(['weather.onecall_enabled' => true]);
        $this->fakeOpenWeather([
            '/data/3.0/onecall' => Http::response(['message' => 'secret internal'], 500),
        ]);

        $this->getJson('/api/weather/onecall/759734')
            ->assertStatus(502)
            ->assertExactJson(['error' => 'Nie udało się pobrać danych pogodowych']);
    }
}
