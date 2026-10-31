<?php

namespace App\Http\Controllers;

use App\Exceptions\WeatherApiException;
use App\Models\City;
use App\Services\WeatherService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CityController extends Controller
{
    public function __construct(protected WeatherService $weather)
    {
    }

    /**
     * Miasta domyślne (config weather.default_cities). Trasa publiczna - jeśli żądanie
     * zawiera token, pomijane są ulubione miasta TEGO użytkownika.
     */
    public function index()
    {
        $cities = $this->weather->ensureCities(config('weather.default_cities', []));

        $user = auth('sanctum')->user();

        if ($user) {
            $favoriteIds = $user->cities()->pluck('cities.id')->all();
            $cities = $cities->reject(fn (City $city) => in_array($city->id, $favoriteIds))->values();
        }

        return $cities;
    }

    public function getUserCities(Request $request)
    {
        // Kolejność ustawiona przez użytkownika, przy równej pozycji - wg daty dodania
        $cities = $request->user()->cities()
            ->with('currentWeather')
            ->orderBy('user_cities.position')
            ->orderBy('user_cities.created_at')
            ->orderBy('user_cities.id')
            ->get();

        $cities->each(function ($city) {
            $city->alias = $city->pivot->alias;
            $city->position = (int) $city->pivot->position;
            $city->current_temp = optional($city->currentWeather)->temperature;
            $city->current_condition = optional($city->currentWeather)->weather_condition;
            $city->current_icon = optional($city->currentWeather)->weather_icon;
        });

        return $cities;
    }

    public function addCity(Request $request)
    {
        $request->validate([
            'city_id' => 'required|integer|min:1'
        ]);

        $cityId = (int) $request->city_id;
        $user = $request->user();
        $limit = (int) config('weather.favorites_limit', 10);

        if ($user->cities()->count() >= $limit) {
            return response()->json(['error' => 'Osiągnięto limit ' . $limit . ' ulubionych miast'], 400);
        }

        try {
            $city = $this->weather->findOrCreateCity($cityId);
        } catch (WeatherApiException $e) {
            if ($e->isNotFound()) {
                return response()->json(['error' => 'Nie znaleziono miasta'], 404);
            }

            return response()->json(['error' => 'Wystąpił błąd podczas dodawania miasta'], 502);
        }

        if ($user->cities()->where('city_id', $city->id)->exists()) {
            return response()->json(['error' => 'Miasto już jest w ulubionych'], 400);
        }

        // Nowe ulubione trafia na koniec listy
        $position = (int) $user->cities()->max('user_cities.position') + ($user->cities()->exists() ? 1 : 0);
        $user->cities()->attach($city->id, ['position' => $position]);

        return response()->json([
            'message' => 'Miasto dodane do ulubionych',
            'city' => $city
        ]);
    }

    // Usuwa miasto z ulubionych. Samo miasto i jego historia pozostają w bazie.
    public function removeCity(Request $request, int $cityId)
    {
        $city = City::where('openweather_id', $cityId)->first();

        if (!$city) {
            return response()->json(['error' => 'Nie znaleziono miasta'], 404);
        }

        $request->user()->cities()->detach($city->id);

        return response()->json(['message' => 'Miasto usunięte z ulubionych']);
    }

    public function searchCities(Request $request)
    {
        $query = trim((string) $request->get('q'));

        if (mb_strlen($query) < 3) {
            return response()->json(['error' => 'Zapytanie musi zawierać co najmniej 3 znaki'], 400);
        }

        if (mb_strlen($query) > 100) {
            return response()->json(['error' => 'Zapytanie jest zbyt długie'], 400);
        }

        try {
            return response()->json($this->weather->searchCities($query));
        } catch (WeatherApiException $e) {
            return response()->json(['error' => 'Wystąpił błąd podczas wyszukiwania miast'], 502);
        }
    }

    /**
     * Dane historyczne ulubionego miasta. Parametry opcjonalne: from, to (data lub data z godziną, ISO 8601).
     * Domyślnie ostatnie 7 dni, maksymalny zakres 366 dni.
     */
    public function getHistoricalData(Request $request, int $cityId)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        [$from, $to] = $this->historyRange($request);

        $city = City::where('openweather_id', $cityId)->first();

        if (!$city) {
            return response()->json(['error' => 'Nie znaleziono miasta'], 404);
        }

        $isFavorite = $request->user()->cities()->where('city_id', $city->id)->exists();

        if (!$isFavorite) {
            return response()->json(['error' => 'Brak dostępu do danych historycznych dla tego miasta'], 403);
        }

        $historicalData = $city->weatherData()
            ->whereBetween('measured_at', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')])
            ->orderBy('measured_at', 'asc')
            ->get()
            ->map(function ($data) {
                return [
                    'timestamp' => $data->measured_at->timestamp * 1000, // Dla JavaScript Date
                    'date' => $data->measured_at->format('Y-m-d H:i:s'),
                    'temperature' => $data->temperature,
                    'humidity' => $data->humidity,
                    'pressure' => $data->pressure,
                    'wind_speed' => $data->wind_speed,
                    'weather_condition' => $data->weather_condition,
                    'weather_icon' => $data->weather_icon,
                    'feels_like' => $data->feels_like,
                    'wind_direction' => $data->wind_direction,
                    'clouds' => $data->clouds,
                    'visibility' => $data->visibility,
                    'weather_description' => $data->weather_description,
                ];
            });

        return response()->json([
            'city' => [
                'id' => $city->id,
                'name' => $city->name,
                'country' => $city->country
            ],
            'data' => $historicalData
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function historyRange(Request $request): array
    {
        $tz = config('app.timezone');
        $isDateOnly = fn ($value) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value);

        $to = $request->filled('to')
            ? Carbon::parse($request->to)->setTimezone($tz)
            : now($tz);

        if ($request->filled('to') && $isDateOnly($request->to)) {
            $to = $to->endOfDay();
        }

        $from = $request->filled('from')
            ? Carbon::parse($request->from)->setTimezone($tz)
            : $to->copy()->subDays(config('weather.history_default_days', 7));

        if ($request->filled('from') && $isDateOnly($request->from)) {
            $from = $from->startOfDay();
        }

        if ($from->gt($to)) {
            throw ValidationException::withMessages([
                'from' => ['Data początkowa musi być wcześniejsza niż data końcowa.'],
            ]);
        }

        $maxDays = (int) config('weather.history_max_days', 366);

        if ($from->diffInDays($to) > $maxDays) {
            throw ValidationException::withMessages([
                'from' => ['Maksymalny zakres danych historycznych to ' . $maxDays . ' dni.'],
            ]);
        }

        return [$from, $to];
    }
}
