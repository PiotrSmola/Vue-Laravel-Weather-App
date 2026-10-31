<?php

namespace App\Console\Commands;

use App\Exceptions\WeatherApiException;
use App\Models\AlertRule;
use App\Models\City;
use App\Services\Alerts\AlertEvaluator;
use App\Services\WeatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

class EvaluateForecastAlerts extends Command
{
    protected $signature = 'alerts:forecast';

    protected $description = 'Sprawdza alerty prognozy (deszcz jutro, przymrozek) dla miast z aktywnymi regułami';

    public function handle(WeatherService $weather, AlertEvaluator $evaluator)
    {
        $cityIds = AlertRule::query()
            ->active()
            ->forFavoriteCities()
            ->whereIn('type', AlertRule::FORECAST_TYPES)
            ->distinct()
            ->pluck('city_id');

        if ($cityIds->isEmpty()) {
            $this->info('Brak aktywnych alertów prognozy.');
            return Command::SUCCESS;
        }

        $cities = City::whereIn('id', $cityIds)->orderBy('id')->get();
        $delay = (int) config('weather.update_delay_ms', 1000);
        $triggered = 0;
        $failed = 0;

        foreach ($cities as $index => $city) {
            if ($index > 0 && $delay > 0) {
                Sleep::for($delay)->milliseconds();
            }

            try {
                // Prognoza z cache (domyślnie 30 min) - oszczędzamy limit darmowego API
                $forecast = $weather->forecastForCity($city, config('weather.default_lang', 'pl'));
                $count = $evaluator->evaluateForecast($city, $forecast);
                $triggered += $count;
                $this->info("{$city->name}: wyzwolone alerty: {$count}");
            } catch (WeatherApiException $e) {
                $failed++;
                $this->error('Nie udało się pobrać prognozy dla miasta: ' . $city->name);
            } catch (\Throwable $e) {
                $failed++;
                $this->error('Wystąpił błąd dla miasta: ' . $city->name);
                Log::error('Wyjątek w alerts:forecast dla miasta ' . $city->id . ': ' . $e->getMessage());
            }
        }

        $this->info("Sprawdzono miast: {$cities->count()}, wyzwolone alerty: {$triggered}, błędy: {$failed}");

        return Command::SUCCESS;
    }
}
