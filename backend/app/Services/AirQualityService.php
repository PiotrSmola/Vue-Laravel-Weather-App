<?php

namespace App\Services;

use App\Events\AirQualityUpdated;
use App\Exceptions\WeatherApiException;
use App\Models\AirQualityData;
use App\Models\City;
use App\Support\AirQualityIndex;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Jakość powietrza z OpenWeather Air Pollution API (darmowy plan):
 * aktualne dane, prognoza godzinowa (~4-5 dni), zapis historii pomiarów.
 */
class AirQualityService
{
    public const CURRENT_PATH = '/data/2.5/air_pollution';
    public const FORECAST_PATH = '/data/2.5/air_pollution/forecast';

    public function __construct(protected OpenWeatherClient $client)
    {
    }

    /**
     * Aktualna jakość powietrza dla miasta (cache). Świeżo pobrany pomiar trafia do historii.
     *
     * @throws WeatherApiException
     */
    public function currentForCity(City $city): ?array
    {
        $cacheKey = $this->cityKey('current', $city);

        if (($cached = Cache::get($cacheKey)) !== null) {
            return $this->formatCurrent($cached, $city);
        }

        return $this->formatCurrent($this->refresh($city), $city);
    }

    /**
     * Pobiera aktualne dane z API z pominięciem cache, zapisuje pomiar i odświeża cache.
     * Zwraca surową odpowiedź API.
     *
     * @throws WeatherApiException
     */
    public function refresh(City $city): array
    {
        $payload = $this->client->get(self::CURRENT_PATH, $this->cityQuery($city));

        if ($entry = $this->firstEntry($payload)) {
            $this->storeMeasurement($city, $entry);
        }

        Cache::put($this->cityKey('current', $city), $payload, now()->addMinutes(config('air.cache.current', 15)));

        return $payload;
    }

    /**
     * Aktualna jakość powietrza dla współrzędnych (bez zapisu w historii).
     *
     * @throws WeatherApiException
     */
    public function currentForCoordinates(float $lat, float $lon): ?array
    {
        $cacheKey = 'air:current:coords:' . round($lat, 4) . ':' . round($lon, 4);

        $payload = Cache::get($cacheKey);

        if ($payload === null) {
            $payload = $this->client->get(self::CURRENT_PATH, ['lat' => $lat, 'lon' => $lon]);
            Cache::put($cacheKey, $payload, now()->addMinutes(config('air.cache.current', 15)));
        }

        return $this->formatCurrent($payload);
    }

    /**
     * Prognoza godzinowa jakości powietrza dla miasta (aqi, PM2.5, PM10).
     *
     * @throws WeatherApiException
     */
    public function forecastForCity(City $city): array
    {
        $cacheKey = $this->cityKey('forecast', $city);

        $payload = Cache::get($cacheKey);

        if ($payload === null) {
            $payload = $this->client->get(self::FORECAST_PATH, $this->cityQuery($city));
            Cache::put($cacheKey, $payload, now()->addMinutes(config('air.cache.forecast', 30)));
        }

        $list = collect($payload['list'] ?? [])
            ->filter(fn ($entry) => isset($entry['dt'], $entry['main']['aqi']))
            ->sortBy('dt')
            ->map(function ($entry) {
                $aqi = (int) $entry['main']['aqi'];

                return [
                    'timestamp' => (int) $entry['dt'] * 1000, // Dla JavaScript Date
                    'date' => $this->timestamp($entry['dt']),
                    'aqi' => $aqi,
                    'label' => AirQualityIndex::owmLabel($aqi),
                    'pm2_5' => $this->number(data_get($entry, 'components.pm2_5')),
                    'pm10' => $this->number(data_get($entry, 'components.pm10')),
                ];
            })
            ->values()
            ->all();

        return [
            'city' => $this->cityInfo($city),
            'list' => $list,
        ];
    }

    /**
     * Zapisuje pomiar (wpis z listy odpowiedzi API). Klucz unikalności: (city_id, measured_at).
     * Zdarzenie AirQualityUpdated jest wysyłane tylko dla NOWEGO wiersza.
     */
    public function storeMeasurement(City $city, array $entry): AirQualityData
    {
        $values = ['aqi' => (int) data_get($entry, 'main.aqi', 0)];

        foreach (AirQualityData::COMPONENTS as $component) {
            $values[$component] = $this->number(data_get($entry, 'components.' . $component));
        }

        $airQuality = AirQualityData::updateOrCreate(
            ['city_id' => $city->id, 'measured_at' => $this->timestamp($entry['dt'] ?? time())],
            $values
        );

        if ($airQuality->wasRecentlyCreated) {
            AirQualityUpdated::dispatch($city, $airQuality);
        }

        return $airQuality;
    }

    /**
     * Odpowiedź API -> dane dla frontendu (null, gdy API nie zwróciło żadnego pomiaru).
     */
    public function formatCurrent(array $payload, ?City $city = null): ?array
    {
        $entry = $this->firstEntry($payload);

        if (!$entry) {
            return null;
        }

        $components = [];
        foreach (AirQualityData::COMPONENTS as $component) {
            $components[$component] = $this->number(data_get($entry, 'components.' . $component));
        }

        $aqi = (int) data_get($entry, 'main.aqi', 0);

        return [
            'city' => $city ? $this->cityInfo($city) : null,
            'coord' => [
                'lat' => (float) data_get($payload, 'coord.lat', $city?->latitude),
                'lon' => (float) data_get($payload, 'coord.lon', $city?->longitude),
            ],
            'timestamp' => (int) $entry['dt'] * 1000,
            'date' => $this->timestamp($entry['dt']),
            'aqi' => $aqi,
            'label' => AirQualityIndex::owmLabel($aqi),
            'components' => $components,
            'dominant_pollutant' => AirQualityIndex::dominant($components),
            // Dodatkowe indeksy liczone z progów stężeń (szacunek - patrz AirQualityIndex)
            'indexes' => [
                'european' => AirQualityIndex::european($components),
                'polish' => AirQualityIndex::polish($components),
            ],
        ];
    }

    protected function firstEntry(array $payload): ?array
    {
        $entry = $payload['list'][0] ?? null;

        return is_array($entry) && isset($entry['dt'], $entry['main']['aqi']) ? $entry : null;
    }

    protected function cityInfo(City $city): array
    {
        return [
            'id' => (int) $city->openweather_id,
            'name' => $city->name,
            'country' => $city->country,
        ];
    }

    protected function cityQuery(City $city): array
    {
        return ['lat' => (float) $city->latitude, 'lon' => (float) $city->longitude];
    }

    protected function cityKey(string $type, City $city): string
    {
        return "air:{$type}:city:{$city->openweather_id}";
    }

    protected function number($value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    // Czas uniksowy -> 'Y-m-d H:i:s' w strefie aplikacji (tak jak w WeatherService)
    protected function timestamp(int|string $unix): string
    {
        return Carbon::createFromTimestamp((int) $unix)
            ->setTimezone(config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }
}
