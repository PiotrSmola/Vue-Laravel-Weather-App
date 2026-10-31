<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Porównanie miast: aktualna pogoda + prognoza dla 2-4 miast w zwartym formacie.
 *
 * Korzysta z tego samego cache co WeatherService (te same klucze i czasy życia), więc
 * porównanie nie generuje dodatkowych zapytań do API dla miast oglądanych przed chwilą.
 * Brakujące wpisy są pobierane równolegle (OpenWeatherClient::getMany); zapytania, które
 * się nie powiodły, są ponawiane pojedynczo przez WeatherService - ewentualny błąd
 * (WeatherApiException) obsługuje wtedy globalny handler (404 / 502).
 */
class CompareService
{
    public function __construct(protected WeatherService $weather, protected OpenWeatherClient $client)
    {
    }

    /**
     * @param int[] $ids ID miast OpenWeather
     */
    public function compare(array $ids, string $lang): array
    {
        $currentKey = fn (int $id) => "weather:current:id:{$id}:{$lang}";
        $forecastKey = fn (int $id) => "weather:forecast:id:{$id}:{$lang}";

        $current = [];
        $forecast = [];
        $missing = [];

        foreach ($ids as $id) {
            $current[$id] = Cache::get($currentKey($id));
            $forecast[$id] = Cache::get($forecastKey($id));

            if ($current[$id] === null) {
                $missing["current:{$id}"] = ['/data/2.5/weather', ['id' => $id, 'units' => 'metric', 'lang' => $lang]];
            }

            if ($forecast[$id] === null) {
                $missing["forecast:{$id}"] = ['/data/2.5/forecast', ['id' => $id, 'units' => 'metric', 'lang' => $lang]];
            }
        }

        foreach ($this->client->getMany($missing) as $key => $payload) {
            if ($payload === null) {
                continue;
            }

            [$type, $id] = explode(':', $key);
            $id = (int) $id;

            if ($type === 'current') {
                if (isset($payload['id']) && (int) $payload['id'] > 0) {
                    $city = $this->weather->cityFromPayload($payload);
                    $this->weather->storeMeasurement($city, $payload);
                }

                Cache::put($currentKey($id), $payload, now()->addMinutes(config('weather.cache.current', 10)));
                $current[$id] = $payload;
            } else {
                Cache::put($forecastKey($id), $payload, now()->addMinutes(config('weather.cache.forecast', 30)));
                $forecast[$id] = $payload;
            }
        }

        return array_map(function (int $id) use ($current, $forecast, $lang) {
            $currentPayload = $current[$id] ?? $this->weather->currentForOpenWeatherId($id, $lang);
            $forecastPayload = $forecast[$id] ?? $this->weather->forecastForOpenWeatherId($id, $lang);

            return $this->normalize($id, $currentPayload, $forecastPayload);
        }, $ids);
    }

    protected function normalize(int $id, array $current, array $forecast): array
    {
        return [
            'id' => (int) ($current['id'] ?? $id),
            'name' => $current['name'] ?? data_get($forecast, 'city.name'),
            'country' => data_get($current, 'sys.country', data_get($forecast, 'city.country')),
            'current' => [
                'temp' => data_get($current, 'main.temp'),
                'feels_like' => data_get($current, 'main.feels_like'),
                'humidity' => data_get($current, 'main.humidity'),
                'pressure' => data_get($current, 'main.pressure'),
                'wind_speed' => data_get($current, 'wind.speed'),
                'wind_deg' => data_get($current, 'wind.deg'),
                'condition' => data_get($current, 'weather.0.main'),
                'description' => data_get($current, 'weather.0.description'),
                'icon' => data_get($current, 'weather.0.icon'),
                'dt' => data_get($current, 'dt'),
            ],
            'forecast' => array_map(fn ($item) => [
                'dt' => $item['dt'] ?? null,
                'temp' => data_get($item, 'main.temp'),
                'pop' => data_get($item, 'pop', 0),
                'wind_speed' => data_get($item, 'wind.speed'),
                'icon' => data_get($item, 'weather.0.icon'),
            ], $forecast['list'] ?? []),
        ];
    }
}
