<?php

namespace App\Services\Alerts;

use App\Models\AlertEvent;
use App\Models\AlertRule;
use App\Models\City;
use App\Models\WeatherData;
use App\Notifications\AlertTriggered;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Ocena reguł alertów i wyzwalanie powiadomień.
 *  - reguły "bieżące" (temperatura, wiatr, ...) - dla nowego pomiaru miasta,
 *  - reguły prognozy (deszcz jutro, przymrozek) - dla prognozy 5-dniowej /data/2.5/forecast.
 * Cooldown reguły i klucz deduplikacji zapobiegają powtórnym powiadomieniom.
 */
class AlertEvaluator
{
    // Grupy zjawisk -> wartości weather.main z OpenWeather
    public const CONDITION_GROUPS = [
        'rain' => ['Rain', 'Drizzle'],
        'snow' => ['Snow'],
        'thunderstorm' => ['Thunderstorm'],
        'fog' => ['Fog', 'Mist', 'Haze'],
    ];

    public function __construct(protected AlertFormatter $formatter)
    {
    }

    /**
     * Ocena reguł bieżących dla nowego pomiaru. Zwraca liczbę wyzwolonych alertów.
     */
    public function evaluateCurrent(City $city, WeatherData $weatherData): int
    {
        $rules = $this->activeRules($city, AlertRule::CURRENT_TYPES);

        return $this->evaluateRules($rules, $city, fn (AlertRule $rule) => $this->checkCurrent($rule, $weatherData));
    }

    /**
     * Ocena reguł prognozy dla miasta (odpowiedź /data/2.5/forecast). Zwraca liczbę wyzwolonych alertów.
     */
    public function evaluateForecast(City $city, array $forecast): int
    {
        $rules = $this->activeRules($city, AlertRule::FORECAST_TYPES);

        return $this->evaluateRules($rules, $city, fn (AlertRule $rule) => $this->checkForecast($rule, $forecast));
    }

    public function checkCurrent(AlertRule $rule, WeatherData $data): ?AlertCheckResult
    {
        $threshold = $rule->effectiveThreshold();

        return match ($rule->type) {
            'temp_below' => $this->compare((float) $data->temperature, '<', $threshold),
            'temp_above' => $this->compare((float) $data->temperature, '>', $threshold),
            'wind_above' => $this->compare((float) $data->wind_speed, '>', $threshold),
            'humidity_above' => $this->compare((float) $data->humidity, '>', $threshold),
            'pressure_below' => $this->compare((float) $data->pressure, '<', $threshold),
            'condition' => $this->checkCondition($rule, $data),
            default => null,
        };
    }

    public function checkForecast(AlertRule $rule, array $forecast): ?AlertCheckResult
    {
        return match ($rule->type) {
            'rain_forecast_tomorrow' => $this->checkRainTomorrow($rule, $forecast),
            'frost_forecast' => $this->checkFrost($rule, $forecast),
            default => null,
        };
    }

    // Wspólna pętla: sprawdzenie reguły, cooldown, deduplikacja, zapis zdarzenia i powiadomienie
    protected function evaluateRules(Collection $rules, City $city, callable $check): int
    {
        $triggered = 0;

        foreach ($rules as $rule) {
            try {
                if ($rule->isCoolingDown()) {
                    continue;
                }

                $result = $check($rule);

                if ($result && $this->trigger($rule, $city, $result)) {
                    $triggered++;
                }
            } catch (\Throwable $e) {
                Log::error('Błąd oceny reguły alertu ' . $rule->id . ': ' . $e->getMessage());
            }
        }

        return $triggered;
    }

    protected function activeRules(City $city, array $types): Collection
    {
        return AlertRule::query()
            ->active()
            ->forFavoriteCities()
            ->where('city_id', $city->id)
            ->whereIn('type', $types)
            ->with('user')
            ->get();
    }

    protected function trigger(AlertRule $rule, City $city, AlertCheckResult $result): bool
    {
        if ($result->dedupeKey !== null
            && AlertEvent::where('alert_rule_id', $rule->id)->where('dedupe_key', $result->dedupeKey)->exists()) {
            return false;
        }

        // Atomowa rezerwacja - równoległe procesy nie wyślą tego samego alertu dwa razy
        if (!$rule->claimTrigger()) {
            return false;
        }

        $user = $rule->user;
        $channels = array_values(array_intersect(AlertRule::CHANNELS, (array) $rule->channels));

        $event = AlertEvent::create([
            'user_id' => $rule->user_id,
            'alert_rule_id' => $rule->id,
            'city_id' => $city->id,
            'type' => $rule->type,
            'value' => $result->value,
            'threshold' => $rule->effectiveThreshold(),
            'title' => $this->formatter->title($rule, $city),
            'message' => $this->formatter->message($rule, $city, $result, $user),
            'channels' => $channels,
            'data' => array_merge($result->details, ['condition' => $rule->condition]),
            'dedupe_key' => $result->dedupeKey,
            'triggered_at' => $rule->last_triggered_at ?? now(),
        ]);

        if ($channels && $user) {
            $user->notify(new AlertTriggered($event));
        }

        return true;
    }

    protected function compare(float $value, string $operator, ?float $threshold): ?AlertCheckResult
    {
        if ($threshold === null) {
            return null;
        }

        $matches = $operator === '<' ? $value < $threshold : $value > $threshold;

        return $matches ? new AlertCheckResult($value) : null;
    }

    protected function checkCondition(AlertRule $rule, WeatherData $data): ?AlertCheckResult
    {
        $mains = self::CONDITION_GROUPS[$rule->condition] ?? [];

        if (!in_array($data->weather_condition, $mains, true)) {
            return null;
        }

        return new AlertCheckResult(null, [
            'weather_condition' => $data->weather_condition,
            'description' => $data->weather_description ?: $this->formatter->conditionLabel($rule->condition),
        ]);
    }

    // Deszcz jutro (czas lokalny miasta): maks. prawdopodobieństwo opadów deszczu >= progu (%)
    protected function checkRainTomorrow(AlertRule $rule, array $forecast): ?AlertCheckResult
    {
        $offset = (int) data_get($forecast, 'city.timezone', 0);
        $tomorrow = now()->utc()->addSeconds($offset)->addDay()->format('Y-m-d');
        $threshold = $rule->effectiveThreshold() ?? 50.0;

        $rainy = collect(data_get($forecast, 'list', []))
            ->filter(fn ($item) => gmdate('Y-m-d', (int) $item['dt'] + $offset) === $tomorrow)
            ->filter(fn ($item) => (float) data_get($item, 'rain.3h', 0) > 0
                || in_array(data_get($item, 'weather.0.main'), ['Rain', 'Drizzle', 'Thunderstorm'], true));

        if ($rainy->isEmpty()) {
            return null;
        }

        $pop = round((float) $rainy->max(fn ($item) => (float) ($item['pop'] ?? 0)) * 100);

        if ($pop < $threshold) {
            return null;
        }

        return new AlertCheckResult($pop, [
            'date' => $tomorrow,
            'rain_mm' => round((float) $rainy->sum(fn ($item) => (float) data_get($item, 'rain.3h', 0)), 1),
        ], 'rain:' . $tomorrow);
    }

    // Przymrozek: minimalna prognozowana temperatura w najbliższych N godzinach < progu (°C)
    protected function checkFrost(AlertRule $rule, array $forecast): ?AlertCheckResult
    {
        $offset = (int) data_get($forecast, 'city.timezone', 0);
        $from = now()->getTimestamp() - 3 * 3600; // wpis prognozy obejmuje 3 godziny
        $to = now()->getTimestamp() + (int) config('alerts.frost_window_hours', 24) * 3600;
        $threshold = $rule->effectiveThreshold() ?? 0.0;

        $coldest = collect(data_get($forecast, 'list', []))
            ->filter(fn ($item) => (int) $item['dt'] > $from && (int) $item['dt'] <= $to)
            ->map(fn ($item) => [
                'dt' => (int) $item['dt'],
                'temp' => (float) data_get($item, 'main.temp_min', data_get($item, 'main.temp')),
            ])
            ->sortBy('temp')
            ->first();

        if (!$coldest || $coldest['temp'] >= $threshold) {
            return null;
        }

        $localDate = gmdate('Y-m-d', $coldest['dt'] + $offset);

        return new AlertCheckResult($coldest['temp'], [
            'at' => gmdate('d.m H:i', $coldest['dt'] + $offset),
            'date' => $localDate,
        ], 'frost:' . $localDate);
    }
}
