<?php

namespace Tests\Feature;

use App\Models\AlertEvent;
use App\Notifications\AlertTriggered;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesAlerts;
use Tests\Concerns\FakesOpenWeather;
use Tests\Fixtures\OpenWeatherFixtures;
use Tests\TestCase;

class ForecastAlertsTest extends TestCase
{
    use RefreshDatabase, FakesOpenWeather, CreatesAlerts;

    protected function setUp(): void
    {
        parent::setUp();

        // Prognoza z fixtures zaczyna się 2024-10-31 15:00 UTC (strefa miasta: UTC+1)
        $this->travelTo(Carbon::parse('2024-10-31 12:00:00', 'UTC'));
        Notification::fake();
    }

    // Prognoza z deszczem jutro (czas lokalny 2024-11-01): wpisy 3..7 to "jutro"
    protected function rainyForecast(float $pop = 0.8): array
    {
        $forecast = OpenWeatherFixtures::forecast(759734);
        $forecast['list'][5]['weather'][0]['main'] = 'Rain';
        $forecast['list'][5]['pop'] = $pop;
        $forecast['list'][5]['rain'] = ['3h' => 2.4];
        $forecast['list'][6]['pop'] = 0.95; // wysokie pop, ale bez deszczu (chmury) - nie liczy się

        return $forecast;
    }

    protected function frostForecast(float $min = -2.5): array
    {
        $forecast = OpenWeatherFixtures::forecast(759734);
        $forecast['list'][4]['main']['temp_min'] = $min;

        return $forecast;
    }

    public function test_rain_tomorrow_alert(): void
    {
        $this->fakeOpenWeather(['/data/2.5/forecast' => Http::response($this->rainyForecast())]);
        [$user, $city] = $this->userWithFavorite();
        $rule = $this->makeRule($user, $city, ['type' => 'rain_forecast_tomorrow', 'threshold' => null, 'cooldown_minutes' => 30]);

        $this->artisan('alerts:forecast')->assertSuccessful();

        $event = AlertEvent::sole();
        $this->assertSame('rain_forecast_tomorrow', $event->type);
        $this->assertSame(80.0, $event->value);
        $this->assertSame(50.0, $event->threshold);
        $this->assertSame('rain:2024-11-01', $event->dedupe_key);
        $this->assertStringContainsString('Jutro (2024-11-01)', $event->message);
        $this->assertStringContainsString('2.4 mm', $event->message);
        Notification::assertSentTo($user, AlertTriggered::class);

        // Ta sama prognoza na ten sam dzień nie jest zgłaszana ponownie (nawet po cooldownie)
        $this->travel(3)->hours();
        $this->artisan('alerts:forecast')->assertSuccessful();
        $this->assertSame(1, AlertEvent::count());
        $this->assertSame(1, $rule->events()->count());
    }

    public function test_rain_below_threshold_or_no_rain_does_not_trigger(): void
    {
        // Rzeszów: deszcz z prawdopodobieństwem 40%, Warszawa: prognoza bez deszczu
        $this->fakeOpenWeather(['/data/2.5/forecast' => fn ($request, array $query) => (int) $query['id'] === 759734
            ? Http::response($this->rainyForecast(0.4))
            : Http::response(OpenWeatherFixtures::forecast((int) $query['id']))]);
        [$user, $city] = $this->userWithFavorite();
        $this->makeRule($user, $city, ['type' => 'rain_forecast_tomorrow', 'threshold' => 50]);
        [$second, $warsaw] = $this->userWithFavorite(756135);
        $this->makeRule($second, $warsaw, ['type' => 'rain_forecast_tomorrow', 'threshold' => 10]);

        $this->artisan('alerts:forecast')
            ->expectsOutput('Sprawdzono miast: 2, wyzwolone alerty: 0, błędy: 0')
            ->assertSuccessful();

        $this->assertSame(0, AlertEvent::count());
        Notification::assertNothingSent();
    }

    public function test_rain_today_is_not_tomorrow(): void
    {
        $forecast = OpenWeatherFixtures::forecast(759734);
        $forecast['list'][1]['weather'][0]['main'] = 'Rain'; // 18:00 UTC = 19:00 czasu lokalnego, dziś
        $forecast['list'][1]['pop'] = 1;
        $this->fakeOpenWeather(['/data/2.5/forecast' => Http::response($forecast)]);
        [$user, $city] = $this->userWithFavorite();
        $this->makeRule($user, $city, ['type' => 'rain_forecast_tomorrow', 'threshold' => null]);

        $this->artisan('alerts:forecast')->assertSuccessful();

        $this->assertSame(0, AlertEvent::count());
    }

    public function test_frost_alert_within_24_hours(): void
    {
        $this->fakeOpenWeather(['/data/2.5/forecast' => Http::response($this->frostForecast())]);
        [$user, $city] = $this->userWithFavorite();
        $this->makeRule($user, $city, ['type' => 'frost_forecast', 'threshold' => null, 'channels' => ['mail', 'push']]);

        $this->artisan('alerts:forecast')->assertSuccessful();

        $event = AlertEvent::sole();
        $this->assertSame(-2.5, $event->value);
        $this->assertSame(0.0, $event->threshold);
        // Wpis 4: 2024-11-01 03:00 UTC = 04:00 czasu lokalnego
        $this->assertSame('frost:2024-11-01', $event->dedupe_key);
        $this->assertStringContainsString('-2.5°C', $event->message);
        $this->assertStringContainsString('01.11 04:00', $event->message);
    }

    public function test_frost_outside_window_or_above_threshold_does_not_trigger(): void
    {
        $forecast = OpenWeatherFixtures::forecast(759734, [], 16);
        $forecast['list'][12]['main']['temp_min'] = -6; // 36 h od teraz - poza oknem 24 h
        $this->fakeOpenWeather(['/data/2.5/forecast' => Http::response($forecast)]);
        [$user, $city] = $this->userWithFavorite();
        $this->makeRule($user, $city, ['type' => 'frost_forecast', 'threshold' => null]);
        $this->makeRule($user, $city, ['type' => 'frost_forecast', 'threshold' => -10]);

        $this->artisan('alerts:forecast')->assertSuccessful();

        $this->assertSame(0, AlertEvent::count());
    }

    public function test_custom_frost_threshold(): void
    {
        $this->fakeOpenWeather(['/data/2.5/forecast' => Http::response($this->frostForecast(1.5))]);
        [$user, $city] = $this->userWithFavorite();
        $this->makeRule($user, $city, ['type' => 'frost_forecast', 'threshold' => 0]);
        $this->makeRule($user, $city, ['type' => 'frost_forecast', 'threshold' => 2]);

        $this->artisan('alerts:forecast')->assertSuccessful();

        $this->assertSame(2.0, AlertEvent::sole()->threshold);
    }

    public function test_command_fetches_only_cities_with_active_forecast_rules(): void
    {
        $this->fakeOpenWeather();
        [$user, $city] = $this->userWithFavorite();
        $warsaw = $this->makeCity(756135);
        $krakow = $this->makeCity(3094802);
        $user->cities()->attach([$warsaw->id, $krakow->id]);

        $this->makeRule($user, $city, ['type' => 'frost_forecast', 'threshold' => null]);
        $this->makeRule($user, $city, ['type' => 'rain_forecast_tomorrow', 'threshold' => null]);
        $this->makeRule($user, $warsaw, ['type' => 'temp_below', 'threshold' => 0]);         // bieżący
        $this->makeRule($user, $krakow, ['type' => 'frost_forecast', 'active' => false]); // nieaktywny

        $this->artisan('alerts:forecast')
            ->expectsOutput('Sprawdzono miast: 1, wyzwolone alerty: 0, błędy: 0')
            ->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/data/2.5/forecast') && str_contains($request->url(), 'id=759734'));
    }

    public function test_command_without_rules_sends_no_requests(): void
    {
        $this->fakeOpenWeather();

        $this->artisan('alerts:forecast')
            ->expectsOutput('Brak aktywnych alertów prognozy.')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_api_errors_are_counted(): void
    {
        $this->fakeOpenWeather(['/data/2.5/forecast' => Http::response(OpenWeatherFixtures::error(500, 'boom'), 500)]);
        [$user, $city] = $this->userWithFavorite();
        $this->makeRule($user, $city, ['type' => 'frost_forecast']);

        $this->artisan('alerts:forecast')
            ->expectsOutput('Sprawdzono miast: 1, wyzwolone alerty: 0, błędy: 1')
            ->assertSuccessful();
    }

    public function test_command_is_scheduled_every_three_hours(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'alerts:forecast'));

        $this->assertNotNull($event);
        $this->assertSame('0 */3 * * *', $event->expression);
    }
}
