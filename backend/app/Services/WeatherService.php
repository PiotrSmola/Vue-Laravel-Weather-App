<?php

namespace App\Services;

use App\Events\WeatherUpdated;
use App\Exceptions\WeatherApiException;
use App\Models\City;
use App\Models\WeatherData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Logika pogodowa wspólna dla kontrolerów i komend:
 * tworzenie miast z odpowiedzi API, zapis pomiarów, cache pogody i prognozy.
 */
class WeatherService
{
    public function __construct(protected OpenWeatherClient $client)
    {
    }

    public function client(): OpenWeatherClient
    {
        return $this->client;
    }

    /**
     * Zwraca miasto z bazy, a jeśli go nie ma - pobiera je z API i zapisuje.
     *
     * @throws WeatherApiException
     */
    public function findOrCreateCity(int $openweatherId): City
    {
        $city = City::where('openweather_id', $openweatherId)->first();

        if ($city) {
            return $city;
        }

        $payload = $this->client->current(['id' => $openweatherId], config('weather.default_lang', 'pl'));
        $city = $this->cityFromPayload($payload);
        $this->storeMeasurement($city, $payload);

        return $city;
    }

    /**
     * Zwraca miasta o podanych ID OpenWeather (w tej samej kolejności), tworząc brakujące.
     * Miasta, których nie udało się pobrać z API, są pomijane (błąd trafia do logów).
     */
    public function ensureCities(array $openweatherIds): Collection
    {
        $openweatherIds = array_values(array_unique(array_map('intval', $openweatherIds)));
        $existing = City::whereIn('openweather_id', $openweatherIds)->get()->keyBy('openweather_id');

        return collect($openweatherIds)->map(function (int $id) use ($existing) {
            if ($existing->has($id)) {
                return $existing->get($id);
            }

            try {
                return $this->findOrCreateCity($id);
            } catch (WeatherApiException $e) {
                Log::error('Nie udało się dodać miasta o ID: ' . $id);
                return null;
            }
        })->filter()->values();
    }

    // Tworzy (lub zwraca istniejące) miasto na podstawie odpowiedzi /data/2.5/weather
    public function cityFromPayload(array $payload): City
    {
        $city = City::firstOrCreate(
            ['openweather_id' => (int) $payload['id']],
            [
                'name' => $payload['name'],
                'country' => $payload['sys']['country'] ?? '',
                'latitude' => $payload['coord']['lat'],
                'longitude' => $payload['coord']['lon'],
            ]
        );

        if ($city->wasRecentlyCreated) {
            Log::info('Dodano nowe miasto do bazy: ' . $city->name);
        }

        return $city;
    }

    /**
     * Zapisuje pomiar z odpowiedzi /data/2.5/weather. Klucz unikalności: (city_id, measured_at),
     * więc ten sam pomiar zapisany dwukrotnie nie tworzy duplikatu.
     * Zdarzenie WeatherUpdated jest wysyłane tylko dla NOWEGO wiersza.
     */
    public function storeMeasurement(City $city, array $payload): WeatherData
    {
        $measuredAt = $this->timestamp($payload['dt'] ?? time());

        $weatherData = WeatherData::updateOrCreate(
            ['city_id' => $city->id, 'measured_at' => $measuredAt],
            [
                'temperature' => data_get($payload, 'main.temp', 0),
                'feels_like' => data_get($payload, 'main.feels_like', data_get($payload, 'main.temp', 0)),
                'humidity' => data_get($payload, 'main.humidity', 0),
                'pressure' => data_get($payload, 'main.pressure', 0),
                'wind_speed' => data_get($payload, 'wind.speed', 0),
                'wind_direction' => data_get($payload, 'wind.deg', 0),
                'weather_condition' => data_get($payload, 'weather.0.main', ''),
                'weather_description' => data_get($payload, 'weather.0.description', ''),
                'weather_icon' => data_get($payload, 'weather.0.icon', ''),
                'clouds' => data_get($payload, 'clouds.all', 0),
                'visibility' => data_get($payload, 'visibility', 0),
                'sunrise' => $this->timestamp(data_get($payload, 'sys.sunrise', $payload['dt'] ?? time())),
                'sunset' => $this->timestamp(data_get($payload, 'sys.sunset', $payload['dt'] ?? time())),
                'data_json' => $payload,
            ]
        );

        if ($weatherData->wasRecentlyCreated) {
            WeatherUpdated::dispatch($city, $weatherData);
        }

        return $weatherData;
    }

    /**
     * Aktualna pogoda dla miasta (surowa odpowiedź API, jednostki metryczne).
     *
     * @throws WeatherApiException
     */
    public function currentForCity(City $city, string $lang = 'pl'): array
    {
        return $this->currentForOpenWeatherId((int) $city->openweather_id, $lang);
    }

    /**
     * Aktualna pogoda po ID OpenWeather. Miasto jest tworzone w bazie, jeśli go nie ma,
     * a pomiar zapisywany w historii.
     *
     * @throws WeatherApiException
     */
    public function currentForOpenWeatherId(int $openweatherId, string $lang = 'pl'): array
    {
        return $this->rememberCurrent("weather:current:id:{$openweatherId}:{$lang}", ['id' => $openweatherId], $lang);
    }

    /**
     * Aktualna pogoda po współrzędnych lub nazwie (['lat' =>, 'lon' =>] lub ['q' =>]).
     *
     * @throws WeatherApiException
     */
    public function currentByQuery(array $query, string $lang = 'pl'): array
    {
        return $this->rememberCurrent('weather:current:' . $this->queryKey($query) . ":{$lang}", $query, $lang);
    }

    /**
     * @throws WeatherApiException
     */
    public function forecastForCity(City $city, string $lang = 'pl'): array
    {
        return $this->forecastForOpenWeatherId((int) $city->openweather_id, $lang);
    }

    /**
     * @throws WeatherApiException
     */
    public function forecastForOpenWeatherId(int $openweatherId, string $lang = 'pl'): array
    {
        return $this->rememberForecast("weather:forecast:id:{$openweatherId}:{$lang}", ['id' => $openweatherId], $lang);
    }

    /**
     * @throws WeatherApiException
     */
    public function forecastByQuery(array $query, string $lang = 'pl'): array
    {
        return $this->rememberForecast('weather:forecast:' . $this->queryKey($query) . ":{$lang}", $query, $lang);
    }

    /**
     * Wyszukiwanie miast (geocoding). Każdy wynik dostaje ID OpenWeather najbliższego miasta,
     * pobierane równolegle i cache'owane per współrzędne.
     * Zwraca [{id, name, country, state, lat, lon}].
     *
     * @throws WeatherApiException
     */
    public function searchCities(string $query): array
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query)));
        $cacheKey = 'weather:search:' . md5($normalized);

        if (($cached = Cache::get($cacheKey)) !== null) {
            return $cached;
        }

        $results = $this->client->geocode($normalized, 5);
        $ids = $this->cityIdsForCoordinates(array_map(
            fn ($city) => ['lat' => $city['lat'], 'lon' => $city['lon']],
            $results
        ));

        $complete = !in_array(false, $ids, true);

        $cities = collect($results)->map(function ($city, $index) use ($ids) {
            return [
                'id' => $ids[$index] ?: null,
                'name' => $city['name'],
                'country' => $city['country'] ?? null,
                'state' => $city['state'] ?? null,
                'lat' => $city['lat'],
                'lon' => $city['lon'],
            ];
        })->filter(fn ($city) => $city['id'] !== null)->values()->all();

        // Nie cache'ujemy wyników niepełnych (część zapytań o ID się nie powiodła)
        if ($complete) {
            Cache::put($cacheKey, $cities, now()->addMinutes(config('weather.cache.search', 60)));
        }

        return $cities;
    }

    /**
     * Zwraca ID OpenWeather dla listy współrzędnych (ten sam indeks co wejście).
     * false = błąd zapytania. Brakujące wpisy w cache są pobierane równolegle przez Http::pool.
     */
    public function cityIdsForCoordinates(array $coordinates): array
    {
        $ids = [];
        $missing = [];

        foreach ($coordinates as $index => $coords) {
            $key = $this->coordsLookupKey($coords['lat'], $coords['lon']);
            $cached = Cache::get($key);

            if ($cached !== null) {
                $ids[$index] = (int) $cached;
            } else {
                $missing[$index] = ['/data/2.5/weather', ['lat' => $coords['lat'], 'lon' => $coords['lon'], 'units' => 'metric']];
            }
        }

        foreach ($this->client->getMany($missing) as $index => $payload) {
            $id = $payload['id'] ?? null;

            if ($id) {
                $coords = $coordinates[$index];
                Cache::put($this->coordsLookupKey($coords['lat'], $coords['lon']), (int) $id,
                    now()->addMinutes(config('weather.cache.coords_lookup', 60 * 24)));
            }

            $ids[$index] = $id ? (int) $id : false;
        }

        ksort($ids);

        return $ids;
    }

    protected function rememberCurrent(string $cacheKey, array $query, string $lang): array
    {
        if (($cached = Cache::get($cacheKey)) !== null) {
            return $cached;
        }

        $payload = $this->client->current($query, $lang);

        if (isset($payload['id']) && (int) $payload['id'] > 0) {
            $city = $this->cityFromPayload($payload);
            $this->storeMeasurement($city, $payload);
        }

        Cache::put($cacheKey, $payload, now()->addMinutes(config('weather.cache.current', 10)));

        return $payload;
    }

    protected function rememberForecast(string $cacheKey, array $query, string $lang): array
    {
        if (($cached = Cache::get($cacheKey)) !== null) {
            return $cached;
        }

        $payload = $this->client->forecast($query, $lang);

        Cache::put($cacheKey, $payload, now()->addMinutes(config('weather.cache.forecast', 30)));

        return $payload;
    }

    protected function queryKey(array $query): string
    {
        if (isset($query['lat'], $query['lon'])) {
            return 'coords:' . round((float) $query['lat'], 4) . ':' . round((float) $query['lon'], 4);
        }

        if (isset($query['id'])) {
            return 'id:' . (int) $query['id'];
        }

        return 'q:' . md5(mb_strtolower(trim((string) ($query['q'] ?? ''))));
    }

    protected function coordsLookupKey($lat, $lon): string
    {
        return 'weather:coords-id:' . round((float) $lat, 2) . ':' . round((float) $lon, 2);
    }

    // Czas uniksowy -> 'Y-m-d H:i:s' w strefie aplikacji (tak jak dotychczasowe dane)
    protected function timestamp(int|string $unix): string
    {
        return Carbon::createFromTimestamp((int) $unix)
            ->setTimezone(config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }
}
