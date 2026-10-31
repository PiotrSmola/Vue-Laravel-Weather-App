<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\WeatherService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mapa pogodowa: proxy kafelków OpenWeather oraz lista miast ze współrzędnymi
 * i ostatnim zapisanym pomiarem (bez zapytań do API o każde miasto).
 */
class MapController extends Controller
{
    public function __construct(protected WeatherService $weather)
    {
    }

    /**
     * GET /api/map/tiles/{layer}/{z}/{x}/{y}.png
     * Kafelek warstwy pogodowej. Klucz API dodawany po stronie serwera, obraz cache'owany.
     */
    public function tile(string $layer, int $z, int $x, int $y)
    {
        if (!in_array($layer, config('weather.map.layers', []), true)) {
            return response()->json(['error' => 'Nieznana warstwa mapy'], 404);
        }

        $maxZoom = (int) config('weather.map.max_zoom', 12);
        $max = 2 ** min($z, 30) - 1;

        if ($z < 0 || $z > $maxZoom || $x < 0 || $y < 0 || $x > $max || $y > $max) {
            return response()->json(['error' => 'Nieprawidłowe współrzędne kafelka'], 422);
        }

        $minutes = (int) config('weather.map.tile_cache_minutes', 30);
        $cache = Cache::store(config('weather.map.tile_cache_store') ?: null);
        $key = "weather:tile:{$layer}:{$z}:{$x}:{$y}";

        // Obraz przechowywany jako base64 - bezpieczne dla każdego magazynu cache (także bazy danych)
        $encoded = $cache->get($key);

        if ($encoded === null) {
            $bytes = $this->fetchTile($layer, $z, $x, $y);

            if ($bytes === null) {
                return response()->json(['error' => 'Nie udało się pobrać kafelka mapy'], 502);
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
