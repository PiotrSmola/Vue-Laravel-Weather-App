<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\WeatherService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Mapa pogodowa: proxy kafelków OpenWeather oraz lista miast ze współrzędnymi
 * i ostatnim zapisanym pomiarem (bez zapytań do API o każde miasto).
 */
class MapController extends Controller
{
    // Osobny licznik pobrań kafelków z OpenWeather (weather.tile_rate_limit_per_minute)
    public const TILE_RATE_LIMIT_KEY = 'openweather-tiles';

    public function __construct(protected WeatherService $weather)
    {
    }

    /**
     * GET /api/map/tiles/{layer}/{z}/{x}/{y}.png
     * Kafelek warstwy pogodowej. Klucz API dodawany po stronie serwera, obraz cache'owany.
     * Gdy limit pobrań kafelków jest wyczerpany lub API zwróci błąd - przezroczysty kafelek
     * (HTTP 200, nagłówek X-Tile-Fallback: 1, krótki czas cache), żeby mapa działała dalej.
     */
    public function tile(string $layer, int $z, int $x, int $y)
    {
        if (!in_array($layer, config('weather.map.layers', []), true)) {
            return response()->json(['error' => 'Nieznana warstwa mapy'], 404);
        }

        $maxZoom = (int) config('weather.map.max_zoom', 10);
        $max = 2 ** min($z, 30) - 1;

        if ($z < 0 || $z > $maxZoom || $x < 0 || $y < 0 || $x > $max || $y > $max) {
            return response()->json(['error' => 'Nieprawidłowe współrzędne kafelka'], 422);
        }

        $minutes = max(60, (int) config('weather.map.tile_cache_minutes', 60));
        $cache = Cache::store(config('weather.map.tile_cache_store') ?: null);
        $key = "weather:tile:{$layer}:{$z}:{$x}:{$y}";

        // Obraz przechowywany jako base64 - bezpieczne dla każdego magazynu cache (także bazy danych)
        $encoded = $cache->get($key);

        if ($encoded === null) {
            if (!$this->acquireTileBudget()) {
                return $this->fallbackTile();
            }

            $bytes = $this->fetchTile($layer, $z, $x, $y);

            if ($bytes === null) {
                return $this->fallbackTile();
            }

            $encoded = base64_encode($bytes);
            $cache->put($key, $encoded, now()->addMinutes($minutes));
        }

        return response(base64_decode($encoded), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=' . ($minutes * 60),
        ]);
    }

    /**
     * GET /api/map/cities
     * Miasta domyślne oraz (dla zalogowanego) ulubione - ze współrzędnymi i ostatnim
     * zapisanym w bazie pomiarem (temp = null, jeśli brak pomiaru).
     */
    public function cities()
    {
        $defaultIds = array_map('intval', config('weather.default_cities', []));
        $user = auth('sanctum')->user();

        $favorites = $user
            ? $user->cities()->with('currentWeather')->orderBy('user_cities.position')->get()->keyBy('openweather_id')
            : collect();

        // Brakujące miasta domyślne są tworzone jednorazowo (później wyłącznie odczyt z bazy).
        // Pomiary miast domyślnych i ulubionych odświeża harmonogram (weather:update).
        $this->weather->ensureCities($defaultIds);

        $defaults = City::with('currentWeather')
            ->whereIn('openweather_id', $defaultIds)
            ->get()
            ->keyBy('openweather_id');

        // Najpierw ulubione (w kolejności użytkownika), potem pozostałe miasta domyślne
        $cities = $favorites->values()->concat(
            collect($defaultIds)
                ->reject(fn ($id) => $favorites->has($id))
                ->map(fn ($id) => $defaults->get($id))
                ->filter()
        );

        return $cities->map(function (City $city) use ($favorites, $defaultIds) {
            $weather = $city->currentWeather;
            $favorite = $favorites->get($city->openweather_id);

            return [
                'id' => (int) $city->openweather_id,
                'name' => $city->name,
                'country' => $city->country,
                'lat' => (float) $city->latitude,
                'lon' => (float) $city->longitude,
                'is_favorite' => $favorite !== null,
                'is_default' => in_array((int) $city->openweather_id, $defaultIds, true),
                'alias' => $favorite?->pivot?->alias,
                'temp' => $weather?->temperature,
                'feels_like' => $weather?->feels_like,
                'condition' => $weather?->weather_condition,
                'description' => $weather?->weather_description,
                'icon' => $weather?->weather_icon,
                'measured_at' => $weather?->measured_at?->toIso8601String(),
            ];
        })->values();
    }

    // Rezerwuje jedno pobranie kafelka z osobnego limitu na minutę (0 = bez limitu)
    protected function acquireTileBudget(): bool
    {
        $limit = (int) config('weather.tile_rate_limit_per_minute', 30);

        if ($limit <= 0) {
            return true;
        }

        if (RateLimiter::tooManyAttempts(self::TILE_RATE_LIMIT_KEY, $limit)) {
            return false;
        }

        RateLimiter::hit(self::TILE_RATE_LIMIT_KEY, 60);

        return true;
    }

    // Przezroczysty kafelek 256x256 - przeglądarka spróbuje ponownie po minucie
    protected function fallbackTile()
    {
        return response(self::transparentPng(), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=60',
            'X-Tile-Fallback' => '1',
        ]);
    }

    // Przezroczysty PNG 256x256 (RGBA) budowany bez GD
    public static function transparentPng(): string
    {
        static $png = null;

        if ($png !== null) {
            return $png;
        }

        $chunk = fn (string $type, string $data) => pack('N', strlen($data)) . $type . $data
            . pack('N', crc32($type . $data));

        // Każdy wiersz: bajt filtra (0) + 256 pikseli x 4 bajty (wszystko zero = przezroczyste)
        $raw = str_repeat("\0" . str_repeat("\0", 256 * 4), 256);

        return $png = "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', 256, 256, 8, 6, 0, 0, 0))
            . $chunk('IDAT', gzcompress($raw, 9))
            . $chunk('IEND', '');
    }

    // Pobiera kafelek z OpenWeather. null = błąd (zalogowany, bez szczegółów dla klienta)
    protected function fetchTile(string $layer, int $z, int $x, int $y): ?string
    {
        $url = rtrim((string) config('weather.map.tiles_url'), '/') . "/map/{$layer}/{$z}/{$x}/{$y}.png";

        try {
            $response = Http::timeout((int) config('weather.timeout', 10))
                ->get($url, ['appid' => (string) config('weather.api_key', '')]);
        } catch (ConnectionException $e) {
            Log::error("OpenWeatherMap: błąd połączenia (kafelek {$layer}/{$z}/{$x}/{$y}): " . $e->getMessage());
            return null;
        }

        if (!$response->successful()) {
            Log::error("OpenWeatherMap: błąd kafelka {$layer}/{$z}/{$x}/{$y} (HTTP {$response->status()})");
            return null;
        }

        return $response->body();
    }
}
