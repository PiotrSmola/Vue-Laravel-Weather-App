<?php

namespace App\Http\Controllers;

use App\Exceptions\WeatherApiException;
use App\Services\WeatherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * One Call API 3.0 (opcjonalnie, flaga weather.onecall_enabled / WEATHER_ONECALL_ENABLED).
 * Zwraca indeks UV, oficjalne ostrzeżenia, prognozę 8-dniową i opady w najbliższej godzinie.
 */
class OneCallController extends Controller
{
    // Brak subskrypcji One Call (HTTP 401) - zapamiętywany na godzinę, żeby nie odpytywać API
    public const UNAVAILABLE_CACHE_KEY = 'weather:onecall:unavailable';

    public function __construct(protected WeatherService $weather)
    {
    }

    // GET /api/features - włączone funkcje opcjonalne (publiczne)
    public function features()
    {
        return response()->json([
            'onecall' => self::available(),
        ]);
    }

    // GET /api/weather/onecall/{cityId}
    public function show(Request $request, int $cityId)
    {
        if (!self::available()) {
            return $this->unavailable();
        }

        $lang = $this->lang($request);
        $cacheKey = "weather:onecall:{$cityId}:{$lang}";

        if (($cached = Cache::get($cacheKey)) !== null) {
            return response()->json($cached);
        }

        $city = $this->weather->findOrCreateCity($cityId);

        try {
            $payload = $this->weather->client()->get('/data/3.0/onecall', [
                'lat' => (float) $city->latitude,
                'lon' => (float) $city->longitude,
                'exclude' => 'hourly',
                'units' => 'metric',
                'lang' => $lang,
            ]);
        } catch (WeatherApiException $e) {
            if ($e->status === 401) {
                Cache::put(self::UNAVAILABLE_CACHE_KEY, true, now()->addHour());
                return $this->unavailable();
            }

            throw $e;
        }

        $data = $this->normalize($payload);
        Cache::put($cacheKey, $data, now()->addMinutes((int) config('weather.onecall_cache_minutes', 30)));

        return response()->json($data);
    }

    public static function available(): bool
    {
        return (bool) config('weather.onecall_enabled', false) && !Cache::has(self::UNAVAILABLE_CACHE_KEY);
    }

    protected function unavailable()
    {
        return response()->json(['error' => 'Funkcja niedostępna'], 404);
    }

    protected function normalize(array $payload): array
    {
        $weather = fn (array $item) => [
            'main' => data_get($item, 'weather.0.main'),
            'description' => data_get($item, 'weather.0.description'),
            'icon' => data_get($item, 'weather.0.icon'),
        ];

        return [
            'timezone_offset' => (int) ($payload['timezone_offset'] ?? 0),
            'uvi' => data_get($payload, 'current.uvi'),
            'alerts' => collect($payload['alerts'] ?? [])->map(fn ($alert) => [
                'sender' => $alert['sender_name'] ?? null,
                'event' => $alert['event'] ?? '',
                'start' => $alert['start'] ?? null,
                'end' => $alert['end'] ?? null,
                'description' => $alert['description'] ?? '',
                'tags' => array_values($alert['tags'] ?? []),
            ])->values()->all(),
            'daily' => collect($payload['daily'] ?? [])->take(8)->map(fn ($day) => [
                'dt' => $day['dt'] ?? null,
                'summary' => $day['summary'] ?? null,
                'temp_min' => data_get($day, 'temp.min'),
                'temp_max' => data_get($day, 'temp.max'),
                'temp_day' => data_get($day, 'temp.day'),
                'temp_night' => data_get($day, 'temp.night'),
                'pop' => $day['pop'] ?? null,
                'rain' => $day['rain'] ?? null,
                'snow' => $day['snow'] ?? null,
                'uvi' => $day['uvi'] ?? null,
                'wind_speed' => $day['wind_speed'] ?? null,
                'weather' => $weather($day),
            ])->values()->all(),
            'minutely' => collect($payload['minutely'] ?? [])->map(fn ($minute) => [
                'dt' => $minute['dt'] ?? null,
                'precipitation' => $minute['precipitation'] ?? 0,
            ])->values()->all(),
        ];
    }
}
