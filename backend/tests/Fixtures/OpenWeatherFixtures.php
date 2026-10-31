<?php

namespace Tests\Fixtures;

/**
 * Przykładowe odpowiedzi API OpenWeatherMap (jednostki metryczne) do użycia z Http::fake().
 * Każda metoda przyjmuje $overrides scalane rekurencyjnie z domyślnym payloadem.
 */
class OpenWeatherFixtures
{
    // Miasta domyślne z config/weather.php: [openweather_id => [nazwa, lat, lon]]
    public const CITIES = [
        756135 => ['Warszawa', 52.2298, 21.0118],
        3094802 => ['Kraków', 50.0833, 19.9167],
        3081368 => ['Wrocław', 51.1, 17.0333],
        3088171 => ['Poznań', 52.4069, 16.9299],
        3099434 => ['Gdańsk', 54.3521, 18.6464],
        3093133 => ['Łódź', 51.75, 19.4667],
        3096472 => ['Katowice', 50.2584, 19.0275],
        765876 => ['Lublin', 51.25, 22.5667],
        3085128 => ['Szczecin', 53.4289, 14.553],
        759734 => ['Rzeszów', 50.0413, 21.999],
    ];

    // Odpowiedź /data/2.5/weather
    public static function current(int $id = 759734, array $overrides = []): array
    {
        [$name, $lat, $lon] = self::CITIES[$id] ?? ['Miasto ' . $id, 50.0, 20.0];

        return array_replace_recursive([
            'coord' => ['lon' => $lon, 'lat' => $lat],
            'weather' => [['id' => 800, 'main' => 'Clear', 'description' => 'bezchmurnie', 'icon' => '01d']],
            'base' => 'stations',
            'main' => [
                'temp' => 18.5,
                'feels_like' => 17.9,
                'temp_min' => 17.0,
                'temp_max' => 20.1,
                'pressure' => 1015,
                'humidity' => 60,
            ],
            'visibility' => 10000,
            'wind' => ['speed' => 3.6, 'deg' => 240],
            'clouds' => ['all' => 0],
            'dt' => 1730383200, // 2024-10-31 14:00:00 UTC
            'sys' => ['country' => 'PL', 'sunrise' => 1730353500, 'sunset' => 1730389000],
            'timezone' => 3600,
            'id' => $id,
            'name' => $name,
            'cod' => 200,
        ], $overrides);
    }

    // Odpowiedź /data/2.5/forecast (domyślnie 8 wpisów co 3 godziny)
    public static function forecast(int $id = 759734, array $overrides = [], int $count = 8): array
    {
        [$name, $lat, $lon] = self::CITIES[$id] ?? ['Miasto ' . $id, 50.0, 20.0];
        $start = 1730386800;

        $list = [];
        for ($i = 0; $i < $count; $i++) {
            $dt = $start + $i * 3 * 3600;
            $list[] = [
                'dt' => $dt,
                'main' => [
                    'temp' => 15 + $i,
                    'feels_like' => 14 + $i,
                    'temp_min' => 14 + $i,
                    'temp_max' => 16 + $i,
                    'pressure' => 1013,
                    'humidity' => 70,
                ],
                'weather' => [['id' => 803, 'main' => 'Clouds', 'description' => 'zachmurzenie duże', 'icon' => '04d']],
                'clouds' => ['all' => 75],
                'wind' => ['speed' => 4.1, 'deg' => 200, 'gust' => 6.2],
                'visibility' => 10000,
                'pop' => 0.1,
                'dt_txt' => gmdate('Y-m-d H:i:s', $dt),
            ];
        }

        return array_replace_recursive([
            'cod' => '200',
            'message' => 0,
            'cnt' => $count,
            'list' => $list,
            'city' => [
                'id' => $id,
                'name' => $name,
                'coord' => ['lat' => $lat, 'lon' => $lon],
                'country' => 'PL',
                'timezone' => 3600,
                'sunrise' => 1730353500,
                'sunset' => 1730389000,
            ],
        ], $overrides);
    }

    // Odpowiedź /geo/1.0/direct
    public static function geocode(?array $places = null): array
    {
        return $places ?? [
            ['name' => 'Rzeszów', 'local_names' => ['pl' => 'Rzeszów'], 'lat' => 50.0413, 'lon' => 21.999, 'country' => 'PL', 'state' => 'Podkarpackie Voivodeship'],
            ['name' => 'Rzeszów', 'lat' => 50.04, 'lon' => 22.01, 'country' => 'PL'],
        ];
    }

    // Odpowiedź błędu API (np. 404 dla nieznanego miasta)
    public static function error(int $code = 404, string $message = 'city not found'): array
    {
        return ['cod' => (string) $code, 'message' => $message];
    }
}
