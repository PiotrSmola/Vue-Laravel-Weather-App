<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Rozszerzona historia pomiarów: agregaty dzienne, rekordy, porównania i eksport.
 *
 * Agregacja odbywa się w PHP (a nie w SQL), dzięki czemu działa identycznie na MySQL
 * i na sqlite w testach oraz pozwala grupować dni w strefie czasowej użytkownika.
 * Przy limicie 366 dni i pomiarach co 30 minut to maksymalnie ~17,5 tys. wierszy.
 */
class HistoryService
{
    // Kolumny pomiaru zwracane przez API historii i eksport CSV
    public const COLUMNS = [
        'measured_at', 'temperature', 'feels_like', 'humidity', 'pressure', 'wind_speed', 'wind_direction',
        'weather_condition', 'weather_description', 'weather_icon', 'clouds', 'visibility', 'sunrise', 'sunset',
    ];

    // Tolerancja przy szukaniu pomiaru "o tej samej porze" (wczoraj / tydzień temu)
    public const MATCH_TOLERANCE_MINUTES = 90;

    /**
     * Zakres dat z parametrów from/to - te same zasady co /cities/{cityId}/historical:
     * domyślnie ostatnie 7 dni, sama data = cały dzień, maksymalnie history_max_days dni.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(Request $request): array
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

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

    // Strefa czasowa grupowania dni (?tz=Europe/Warsaw), domyślnie strefa aplikacji
    public function timezone(Request $request): string
    {
        $request->validate([
            'tz' => 'nullable|string|timezone:all',
        ]);

        return $request->query('tz') ?: config('app.timezone');
    }

    // Zapytanie o pomiary miasta w zakresie (rosnąco po czasie pomiaru)
    public function query(City $city, Carbon $from, Carbon $to)
    {
        return $city->weatherData()
            ->select(self::COLUMNS)
            ->whereBetween('measured_at', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')])
            ->orderBy('measured_at');
    }

    /**
     * Agregaty dzienne: min/max/średnia temperatury, średnie wilgotności, ciśnienia,
     * odczuwalnej, średni i maksymalny wiatr, dominujące warunki i liczba pomiarów.
     */
    public function daily(City $city, Carbon $from, Carbon $to, string $tz): array
    {
        return $this->query($city, $from, $to)
            ->get()
            ->groupBy(fn ($row) => $row->measured_at->copy()->setTimezone($tz)->format('Y-m-d'))
            ->map(function (Collection $rows, string $date) {
                [$condition, $icon, $description] = $this->dominantCondition($rows);

                return [
                    'date' => $date,
                    'count' => $rows->count(),
                    'temp_min' => $this->round($rows->min('temperature')),
                    'temp_max' => $this->round($rows->max('temperature')),
                    'temp_avg' => $this->round($rows->avg('temperature')),
                    'feels_like_avg' => $this->round($rows->avg('feels_like')),
                    'humidity_avg' => $this->round($rows->avg('humidity')),
                    'pressure_avg' => $this->round($rows->avg('pressure')),
                    'wind_avg' => $this->round($rows->avg('wind_speed')),
                    'wind_max' => $this->round($rows->max('wind_speed')),
                    'condition' => $condition,
                    'description' => $description,
                    'icon' => $icon,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Rekordy i średnie w zakresie oraz porównania najnowszego pomiaru
     * z tą samą porą wczoraj i tydzień temu, a także średniej z dziś ze średnią z 7 poprzednich dni.
     */
    public function summary(City $city, Carbon $from, Carbon $to, string $tz): array
    {
        $rows = $this->query($city, $from, $to)->get();

        return [
            'count' => $rows->count(),
            'first' => $rows->isEmpty() ? null : $this->moment($rows->first()->measured_at),
            'last' => $rows->isEmpty() ? null : $this->moment($rows->last()->measured_at),
            'records' => $rows->isEmpty() ? null : [
                'temp_max' => $this->record($rows->sortByDesc('temperature')->first(), 'temperature'),
                'temp_min' => $this->record($rows->sortBy('temperature')->first(), 'temperature'),
                'wind_max' => $this->record($rows->sortByDesc('wind_speed')->first(), 'wind_speed'),
                'pressure_max' => $this->record($rows->sortByDesc('pressure')->first(), 'pressure'),
                'pressure_min' => $this->record($rows->sortBy('pressure')->first(), 'pressure'),
                'humidity_max' => $this->record($rows->sortByDesc('humidity')->first(), 'humidity'),
                'humidity_min' => $this->record($rows->sortBy('humidity')->first(), 'humidity'),
            ],
            'averages' => $rows->isEmpty() ? null : [
                'temperature' => $this->round($rows->avg('temperature')),
                'feels_like' => $this->round($rows->avg('feels_like')),
                'humidity' => $this->round($rows->avg('humidity')),
                'pressure' => $this->round($rows->avg('pressure')),
                'wind_speed' => $this->round($rows->avg('wind_speed')),
            ],
            'comparisons' => $this->comparisons($city, $to, $tz),
        ];
    }

    /**
     * Porównania liczone względem najnowszego pomiaru nie późniejszego niż koniec zakresu.
     */
    public function comparisons(City $city, Carbon $to, string $tz): array
    {
        $latest = $city->weatherData()
            ->select(self::COLUMNS)
            ->where('measured_at', '<=', $to->format('Y-m-d H:i:s'))
            ->orderByDesc('measured_at')
            ->first();

        if (!$latest) {
            return ['latest' => null, 'vs_yesterday' => null, 'vs_week_ago' => null, 'today_vs_week' => null];
        }

        $latestValues = $this->values($latest);

        $compareWith = function (Carbon $at) use ($city, $latestValues) {
            $match = $this->closestMeasurement($city, $at);

            if (!$match) {
                return null;
            }

            $values = $this->values($match);

            return $values + ['delta' => $this->delta($latestValues, $values)];
        };

        // "Dziś" = doba (w strefie użytkownika), w której wykonano najnowszy pomiar
        $dayStart = $latest->measured_at->copy()->setTimezone($tz)->startOfDay();
        $todayRows = $this->between($city, $dayStart, $latest->measured_at);
        $weekRows = $this->between($city, $dayStart->copy()->subDays(7), $dayStart->copy()->subSecond());

        $todayVsWeek = null;

        if ($todayRows->isNotEmpty() && $weekRows->isNotEmpty()) {
            $todayAvg = $this->averagesOf($todayRows);
            $weekAvg = $this->averagesOf($weekRows);

            $todayVsWeek = [
                'today' => $todayAvg + ['count' => $todayRows->count()],
                'week' => $weekAvg + ['count' => $weekRows->count()],
                'delta' => $this->delta($todayAvg, $weekAvg),
            ];
        }

        return [
            'latest' => $latestValues,
            'vs_yesterday' => $compareWith($latest->measured_at->copy()->subDay()),
            'vs_week_ago' => $compareWith($latest->measured_at->copy()->subDays(7)),
            'today_vs_week' => $todayVsWeek,
        ];
    }

    /**
     * Wiersz CSV dla pomiaru (separator dziesiętny: przecinek, jak w polskim Excelu).
     */
    public function csvRow($row, string $tz): array
    {
        $number = fn ($value, int $decimals = 1) => $value === null
            ? ''
            : number_format((float) $value, $decimals, ',', '');
        $time = fn ($value) => $value ? Carbon::parse($value)->setTimezone($tz)->format('Y-m-d H:i:s') : '';

        return [
            $time($row->measured_at),
            $number($row->temperature),
            $number($row->feels_like),
            (int) $row->humidity,
            (int) $row->pressure,
            $number($row->wind_speed),
            (int) $row->wind_direction,
            (int) $row->clouds,
            (int) $row->visibility,
            $row->weather_condition,
            $row->weather_description,
            $row->weather_icon,
            $time($row->sunrise),
            $time($row->sunset),
        ];
    }

    public function csvHeader(string $tz): array
    {
        return [
            'Data i godzina (' . $tz . ')',
            'Temperatura (°C)',
            'Temperatura odczuwalna (°C)',
            'Wilgotność (%)',
            'Ciśnienie (hPa)',
            'Prędkość wiatru (m/s)',
            'Kierunek wiatru (°)',
            'Zachmurzenie (%)',
            'Widoczność (m)',
            'Warunki',
            'Opis',
            'Ikona',
            'Wschód słońca',
            'Zachód słońca',
        ];
    }

    // Najbliższy pomiar w oknie ±MATCH_TOLERANCE_MINUTES od podanego czasu
    protected function closestMeasurement(City $city, Carbon $at)
    {
        $tolerance = self::MATCH_TOLERANCE_MINUTES;

        return $this->between($city, $at->copy()->subMinutes($tolerance), $at->copy()->addMinutes($tolerance))
            ->sortBy(fn ($row) => abs($row->measured_at->getTimestamp() - $at->getTimestamp()))
            ->first();
    }

    protected function between(City $city, Carbon $from, Carbon $to): Collection
    {
        $tz = config('app.timezone');

        return $city->weatherData()
            ->select(self::COLUMNS)
            ->whereBetween('measured_at', [
                $from->copy()->setTimezone($tz)->format('Y-m-d H:i:s'),
                $to->copy()->setTimezone($tz)->format('Y-m-d H:i:s'),
            ])
            ->orderBy('measured_at')
            ->get();
    }

    // Najczęstsze warunki w dniu; ikona i opis - najczęstsze w obrębie tych warunków (wariant dzienny)
    protected function dominantCondition(Collection $rows): array
    {
        $condition = $rows->countBy('weather_condition')->sortDesc()->keys()->first();
        $matching = $rows->where('weather_condition', $condition);

        $icon = $matching
            ->map(fn ($row) => preg_replace('/n$/', 'd', (string) $row->weather_icon))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        $description = $matching->countBy('weather_description')->sortDesc()->keys()->first();

        return [$condition ?: null, $icon ?: null, $description ?: null];
    }

    protected function record($row, string $field): array
    {
        return ['value' => $this->round($row->{$field})] + $this->moment($row->measured_at);
    }

    protected function values($row): array
    {
        return $this->moment($row->measured_at) + [
            'temperature' => $this->round($row->temperature),
            'feels_like' => $this->round($row->feels_like),
            'humidity' => (int) $row->humidity,
            'pressure' => (int) $row->pressure,
            'wind_speed' => $this->round($row->wind_speed),
            'condition' => $row->weather_condition,
            'icon' => $row->weather_icon,
        ];
    }

    protected function averagesOf(Collection $rows): array
    {
        return [
            'temperature' => $this->round($rows->avg('temperature')),
            'humidity' => $this->round($rows->avg('humidity')),
            'pressure' => $this->round($rows->avg('pressure')),
            'wind_speed' => $this->round($rows->avg('wind_speed')),
        ];
    }

    // Różnice a - b dla wspólnych pól liczbowych
    protected function delta(array $a, array $b): array
    {
        $delta = [];

        foreach (['temperature', 'feels_like', 'humidity', 'pressure', 'wind_speed'] as $field) {
            if (isset($a[$field], $b[$field])) {
                $delta[$field] = $this->round($a[$field] - $b[$field]);
            }
        }

        return $delta;
    }

    // Czas w formacie zgodnym z /historical: timestamp w ms (dla JS) i data w strefie aplikacji
    protected function moment(Carbon $at): array
    {
        return [
            'timestamp' => $at->getTimestamp() * 1000,
            'date' => $at->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
        ];
    }

    protected function round($value, int $decimals = 1): ?float
    {
        if ($value === null) {
            return null;
        }

        $rounded = round((float) $value, $decimals);

        return $rounded == 0 ? 0.0 : $rounded;
    }
}
