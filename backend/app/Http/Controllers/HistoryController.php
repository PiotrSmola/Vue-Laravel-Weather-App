<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\HistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Rozszerzona historia ulubionego miasta: agregaty dzienne, podsumowanie i eksport CSV.
 * Zasady dostępu i zakresu dat jak w /cities/{cityId}/historical (tylko ulubione, max 366 dni).
 * Opcjonalny parametr ?tz= (np. Europe/Warsaw) - strefa grupowania dni i dat w CSV.
 */
class HistoryController extends Controller
{
    public function __construct(protected HistoryService $history)
    {
    }

    public function daily(Request $request, int $cityId)
    {
        [$from, $to] = $this->history->range($request);
        $tz = $this->history->timezone($request);

        $city = $this->favoriteCity($request, $cityId);

        if ($city instanceof JsonResponse) {
            return $city;
        }

        return response()->json([
            'city' => $this->cityInfo($city),
            'range' => $this->rangeInfo($from, $to, $tz),
            'days' => $this->history->daily($city, $from, $to, $tz),
        ]);
    }

    public function summary(Request $request, int $cityId)
    {
        [$from, $to] = $this->history->range($request);
        $tz = $this->history->timezone($request);

        $city = $this->favoriteCity($request, $cityId);

        if ($city instanceof JsonResponse) {
            return $city;
        }

        return response()->json([
            'city' => $this->cityInfo($city),
            'range' => $this->rangeInfo($from, $to, $tz),
        ] + $this->history->summary($city, $from, $to, $tz));
    }

    // Eksport pomiarów do CSV (UTF-8 z BOM, separator ";", nagłówki po polsku) - strumieniowo
    public function export(Request $request, int $cityId)
    {
        $request->validate([
            'format' => 'nullable|in:csv',
        ]);

        [$from, $to] = $this->history->range($request);
        $tz = $this->history->timezone($request);

        $city = $this->favoriteCity($request, $cityId);

        if ($city instanceof JsonResponse) {
            return $city;
        }

        $filename = sprintf(
            'historia-%s-%s-%s.csv',
            Str::slug($city->name) ?: $city->openweather_id,
            $from->copy()->setTimezone($tz)->format('Y-m-d'),
            $to->copy()->setTimezone($tz)->format('Y-m-d')
        );

        $query = $this->history->query($city, $from, $to);

        return response()->streamDownload(function () use ($query, $tz) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $this->history->csvHeader($tz), ';');

            foreach ($query->lazy(500) as $row) {
                fputcsv($out, $this->history->csvRow($row, $tz), ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    // Miasto z bazy, jeśli jest w ulubionych użytkownika - w przeciwnym razie odpowiedź z błędem
    protected function favoriteCity(Request $request, int $cityId): City|JsonResponse
    {
        $city = City::where('openweather_id', $cityId)->first();

        if (!$city) {
            return response()->json(['error' => 'Nie znaleziono miasta'], 404);
        }

        if (!$request->user()->cities()->where('city_id', $city->id)->exists()) {
            return response()->json(['error' => 'Brak dostępu do danych historycznych dla tego miasta'], 403);
        }

        return $city;
    }

    protected function cityInfo(City $city): array
    {
        return [
            'id' => $city->id,
            'openweather_id' => (int) $city->openweather_id,
            'name' => $city->name,
            'country' => $city->country,
        ];
    }

    protected function rangeInfo($from, $to, string $tz): array
    {
        return [
            'from' => $from->copy()->setTimezone($tz)->toIso8601String(),
            'to' => $to->copy()->setTimezone($tz)->toIso8601String(),
            'tz' => $tz,
        ];
    }
}
