<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Demonstracyjna historia pomiarów: wiarygodne (ale sztuczne!) dane godzinowe dla miasta.
 *
 * Model: średnia sezonowa dla Polski + powolne "fronty" (proces AR(1)) + cykl dobowy
 * (minimum ok. 5:00, maksimum ok. 15:00). Ciśnienie, wilgotność, wiatr i zachmurzenie
 * są powiązane z frontami, a warunki pogodowe wynikają z wilgotności i ciśnienia.
 *
 * Najprościej przez komendę: php artisan weather:demo-history 759734 --days=60
 * albo: php artisan db:seed --class=HistoryDemoSeeder (Rzeszów, 60 dni).
 *
 * Istniejące pomiary nie są nadpisywane (unikalny klucz city_id + measured_at).
 */
class HistoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $city = City::where('openweather_id', 759734)->first()
            ?? City::create([
                'openweather_id' => 759734,
                'name' => 'Rzeszów',
                'country' => 'PL',
                'latitude' => 50.0413,
                'longitude' => 21.999,
            ]);

        $inserted = self::generate($city, 60);

        $this->command?->info("Dodano {$inserted} pomiarów demonstracyjnych dla miasta {$city->name}.");
    }

    /**
     * Generuje pomiary co $intervalMinutes przez ostatnie $days dni (do bieżącej godziny).
     * Zwraca liczbę nowo dodanych wierszy.
     */
    public static function generate(City $city, int $days = 60, int $intervalMinutes = 60, ?int $seed = null): int
    {
        mt_srand($seed ?? crc32((string) $city->openweather_id));

        $tz = config('app.timezone');
        $end = now($tz)->startOfHour();
        $start = $end->copy()->subDays($days);
        $rand = fn (float $min, float $max) => $min + (mt_rand() / mt_getrandmax()) * ($max - $min);

        $front = 0.0;      // anomalia temperatury frontu (°C)
        $pressure = 1015.0;
        $windBase = 3.5;
        $rows = [];
        $inserted = 0;

        for ($at = $start->copy(); $at->lte($end); $at->addMinutes($intervalMinutes)) {
            // Fronty zmieniają się powoli (AR(1) z krokiem godzinowym)
            $front = $front * 0.985 + $rand(-0.45, 0.45);
            $pressure = max(985, min(1040, $pressure + $rand(-0.8, 0.8) - $front * 0.03 - ($pressure - 1015) * 0.01));
            $windBase = max(0.5, min(12, $windBase + $rand(-0.4, 0.4) + (1013 - $pressure) * 0.004));

            $local = $at->copy()->setTimezone('Europe/Warsaw');
            $hour = $local->hour + $local->minute / 60;
            $seasonal = 8.5 - 10.5 * cos(2 * M_PI * ($local->dayOfYear - 15) / 365);
            $diurnal = 4.0 * sin(2 * M_PI * ($hour - 9) / 24);
            $temperature = round($seasonal + $front + $diurnal + $rand(-0.4, 0.4), 1);

            $humidity = (int) round(max(30, min(100, 78 - $diurnal * 4 + (1015 - $pressure) * 0.8 + $rand(-5, 5))));
            $wind = round(max(0, $windBase + $rand(-1.2, 1.2) + max(0, $diurnal) * 0.3), 1);
            $clouds = (int) round(max(0, min(100, 50 + (1015 - $pressure) * 4 + ($humidity - 70) * 1.2 + $rand(-20, 20))));
            $isDay = $hour >= 7 && $hour < 17;

            [$condition, $description, $icon] = match (true) {
                $pressure < 1005 && $humidity > 85 => $temperature <= 0
                    ? ['Snow', 'słabe opady śniegu', '13']
                    : ['Rain', 'słabe opady deszczu', '10'],
                $humidity > 95 && $wind < 2 => ['Mist', 'zamglenia', '50'],
                $clouds > 85 => ['Clouds', 'zachmurzenie duże', '04'],
                $clouds > 50 => ['Clouds', 'pochmurnie z przejaśnieniami', '03'],
                $clouds > 20 => ['Clouds', 'niewielkie zachmurzenie', '02'],
                default => ['Clear', 'bezchmurnie', '01'],
            };

            $windChill = $temperature < 10 ? $wind * 0.7 : 0;
            $sunrise = $local->copy()->setTime(6, 0)->addMinutes((int) round(90 * cos(2 * M_PI * ($local->dayOfYear - 172) / 365) * -1));
            $sunset = $local->copy()->setTime(18, 0)->addMinutes((int) round(150 * cos(2 * M_PI * ($local->dayOfYear - 172) / 365)));

            $rows[] = [
                'city_id' => $city->id,
                'temperature' => $temperature,
                'feels_like' => round($temperature - $windChill + ($humidity > 80 && $temperature > 22 ? 1.5 : 0), 1),
                'humidity' => $humidity,
                'pressure' => (int) round($pressure),
                'wind_speed' => $wind,
                'wind_direction' => (int) round(fmod(240 + $front * 25 + $rand(-40, 40) + 360, 360)),
                'weather_condition' => $condition,
                'weather_description' => $description,
                'weather_icon' => $icon . ($isDay ? 'd' : 'n'),
                'clouds' => $clouds,
                'visibility' => $condition === 'Mist' ? 2000 : ($condition === 'Rain' ? 7000 : 10000),
                'sunrise' => $sunrise->setTimezone($tz)->format('Y-m-d H:i:s'),
                'sunset' => $sunset->setTimezone($tz)->format('Y-m-d H:i:s'),
                'measured_at' => $at->format('Y-m-d H:i:s'),
                'data_json' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($rows) >= 500) {
                $inserted += DB::table('weather_data')->insertOrIgnore($rows);
                $rows = [];
            }
        }

        if ($rows) {
            $inserted += DB::table('weather_data')->insertOrIgnore($rows);
        }

        return $inserted;
    }
}
