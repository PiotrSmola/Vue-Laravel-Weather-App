<?php

namespace App\Http\Controllers;

use App\Exceptions\WeatherApiException;
use App\Models\AirQualityData;
use App\Models\City;
use App\Services\AirQualityService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Jakość powietrza: aktualna, prognoza i historia (ulubione miasta).
 * {cityId} to ID miasta w OpenWeather (cities.openweather_id).
 */
class AirQualityController extends Controller
{
    public function __construct(
        protected AirQualityService $air,
        protected WeatherService $weather,
    ) {
    }

    public function current(int $cityId)
    {
        return $this->guard(function () use ($cityId) {
            $city = $this->weather->findOrCreateCity($cityId);

            return $this->air->currentForCity($city) ?? $this->noData();
        });
    }

    public function coordinates(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
        ]);

        return $this->guard(fn () => $this->air->currentForCoordinates((float) $request->lat, (float) $request->lon)
            ?? $this->noData());
    }

    public function forecast(int $cityId)
    {
        return $this->guard(function () use ($cityId) {
            $city = $this->weather->findOrCreateCity($cityId);

            return $this->air->forecastForCity($city);
        });
    }

    /**
     * Historia jakości powietrza ulubionego miasta. Parametry opcjonalne: from, to (ISO 8601).
     * Domyślnie ostatnie 7 dni, maksymalny zakres 366 dni (jak /cities/{id}/historical).
     */
    public function historical(Request $request, int $cityId)
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

        $data = AirQualityData::where('city_id', $city->id)
            ->whereBetween('measured_at', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')])
            ->orderBy('measured_at', 'asc')
            ->get()
            ->map(function (AirQualityData $row) {
                $item = [
                    'timestamp' => $row->measured_at->timestamp * 1000, // Dla JavaScript Date
                    'date' => $row->measured_at->format('Y-m-d H:i:s'),
                    'aqi' => $row->aqi,
                ];

                foreach (AirQualityData::COMPONENTS as $component) {
                    $item[$component] = $row->{$component};
                }

                return $item;
            });

        return response()->json([
            'city' => [
                'id' => $city->id,
                'name' => $city->name,
                'country' => $city->country,
            ],
            'data' => $data,
        ]);
    }

    // Błędy API: komunikat dotyczący jakości powietrza zamiast ogólnego komunikatu pogodowego
    protected function guard(callable $callback)
    {
        try {
            return $callback();
        } catch (WeatherApiException $e) {
            if ($e->isNotFound()) {
                return response()->json(['error' => 'Nie znaleziono miasta'], 404);
            }

            return response()->json(['error' => 'Nie udało się pobrać danych o jakości powietrza'], 502);
        }
    }

    protected function noData(): JsonResponse
    {
        return response()->json(['error' => 'Brak danych o jakości powietrza dla tej lokalizacji'], 404);
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
            : $to->copy()->subDays(config('air.history_default_days', 7));

        if ($request->filled('from') && $isDateOnly($request->from)) {
            $from = $from->startOfDay();
        }

        if ($from->gt($to)) {
            throw ValidationException::withMessages([
                'from' => ['Data początkowa musi być wcześniejsza niż data końcowa.'],
            ]);
        }

        $maxDays = (int) config('air.history_max_days', 366);

        if ($from->diffInDays($to) > $maxDays) {
            throw ValidationException::withMessages([
                'from' => ['Maksymalny zakres danych historycznych to ' . $maxDays . ' dni.'],
            ]);
        }

        return [$from, $to];
    }
}
