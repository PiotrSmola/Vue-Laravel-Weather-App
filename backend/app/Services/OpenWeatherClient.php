<?php

namespace App\Services;

use App\Exceptions\WeatherApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cienka warstwa nad HTTP API OpenWeatherMap.
 * Zawsze zwraca jednostki metryczne - konwersja jednostek należy do frontendu.
 */
class OpenWeatherClient
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl = 'https://api.openweathermap.org',
        protected int $timeout = 10,
        protected int $retryTimes = 2,
        protected int $retrySleep = 200,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            (string) config('weather.api_key', ''),
            (string) config('weather.api_url', 'https://api.openweathermap.org'),
            (int) config('weather.timeout', 10),
            (int) config('weather.retry_times', 2),
            (int) config('weather.retry_sleep', 200),
        );
    }

    // Aktualna pogoda: $query np. ['id' => 756135], ['lat' => .., 'lon' => ..] lub ['q' => 'Rzeszów']
    public function current(array $query, string $lang = 'pl'): array
    {
        return $this->get('/data/2.5/weather', $query + ['units' => 'metric', 'lang' => $lang]);
    }

    // Prognoza 5 dni / 3 godziny
    public function forecast(array $query, string $lang = 'pl'): array
    {
        return $this->get('/data/2.5/forecast', $query + ['units' => 'metric', 'lang' => $lang]);
    }

    public function geocode(string $q, int $limit = 5): array
    {
        return $this->get('/geo/1.0/direct', ['q' => $q, 'limit' => $limit]);
    }

    public function reverseGeocode(float $lat, float $lon, int $limit = 1): array
    {
        return $this->get('/geo/1.0/reverse', ['lat' => $lat, 'lon' => $lon, 'limit' => $limit]);
    }

    /**
     * Dowolny endpoint API, np. get('/data/2.5/air_pollution', ['lat' => 50, 'lon' => 21])
     * lub get('/data/3.0/onecall', [...]). Klucz API jest dodawany automatycznie.
     *
     * @throws WeatherApiException
     */
    public function get(string $path, array $query = []): array
    {
        try {
            $response = $this->request()->get($this->url($path), $this->withKey($query));
        } catch (ConnectionException $e) {
            Log::error('OpenWeatherMap: błąd połączenia (' . $path . '): ' . $e->getMessage());
            throw new WeatherApiException('Błąd połączenia z API OpenWeatherMap', null, null, $e);
        }

        return $this->decode($path, $response);
    }

    /**
     * Wiele zapytań równolegle (Http::pool). $requests: [klucz => [ścieżka, zapytanie]].
     * Zwraca [klucz => tablica|null] - null oznacza błąd danego zapytania (zalogowany).
     */
    public function getMany(array $requests): array
    {
        if (empty($requests)) {
            return [];
        }

        $responses = Http::pool(function (Pool $pool) use ($requests) {
            foreach ($requests as $key => [$path, $query]) {
                $pool->as((string) $key)
                    ->timeout($this->timeout)
                    ->acceptJson()
                    ->get($this->url($path), $this->withKey($query));
            }
        });

        $results = [];

        foreach ($requests as $key => [$path]) {
            $response = $responses[(string) $key] ?? null;

            try {
                if (!$response instanceof Response) {
                    throw new WeatherApiException('Błąd połączenia z API OpenWeatherMap', null, null,
                        $response instanceof Throwable ? $response : null);
                }

                $results[$key] = $this->decode($path, $response);
            } catch (WeatherApiException $e) {
                $results[$key] = null;
            }
        }

        return $results;
    }

    protected function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retrySleep, function (Throwable $e) {
                // Ponawiamy tylko błędy połączenia, 429 i 5xx - 4xx (np. 404) nie ma sensu powtarzać
                if ($e instanceof ConnectionException) {
                    return true;
                }

                return $e instanceof RequestException
                    && ($e->response->serverError() || $e->response->status() === 429);
            }, throw: false);
    }

    protected function decode(string $path, Response $response): array
    {
        if (!$response->successful()) {
            Log::error('OpenWeatherMap: błąd API (' . $path . ', HTTP ' . $response->status() . '): ' . $response->body());
            throw new WeatherApiException('Błąd API OpenWeatherMap', $response->status(), $response->body());
        }

        $data = $response->json();

        if (!is_array($data)) {
            Log::error('OpenWeatherMap: nieprawidłowa odpowiedź (' . $path . ')');
            throw new WeatherApiException('Nieprawidłowa odpowiedź API OpenWeatherMap', $response->status());
        }

        return $data;
    }

    protected function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    protected function withKey(array $query): array
    {
        return $query + ['appid' => $this->apiKey];
    }
}
