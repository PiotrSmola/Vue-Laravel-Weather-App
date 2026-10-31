<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\WeatherData;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Pojedynczy, wiarygodny pomiar pogody (jednostki metryczne).
 *
 *   WeatherData::factory()->for($city)->at('2026-10-30 12:00:00')->create(['temperature' => 12.5]);
 *
 * @extends Factory<WeatherData>
 */
class WeatherDataFactory extends Factory
{
    protected $model = WeatherData::class;

    // [warunki, opis, ikona (bez sufiksu d/n)]
    public const CONDITIONS = [
        ['Clear', 'bezchmurnie', '01'],
        ['Clouds', 'pochmurnie', '03'],
        ['Clouds', 'zachmurzenie duże', '04'],
        ['Rain', 'słabe opady deszczu', '10'],
        ['Mist', 'zamglenia', '50'],
    ];

    public function definition(): array
    {
        $measuredAt = Carbon::instance($this->faker->dateTimeBetween('-7 days'))->startOfHour();
        [$condition, $description, $icon] = $this->faker->randomElement(self::CONDITIONS);
        $temperature = $this->faker->randomFloat(1, -5, 25);

        return [
            'city_id' => City::factory(),
            'temperature' => $temperature,
            'feels_like' => round($temperature - $this->faker->randomFloat(1, 0, 3), 1),
            'humidity' => $this->faker->numberBetween(40, 98),
            'pressure' => $this->faker->numberBetween(995, 1035),
            'wind_speed' => $this->faker->randomFloat(1, 0, 12),
            'wind_direction' => $this->faker->numberBetween(0, 359),
            'weather_condition' => $condition,
            'weather_description' => $description,
            'weather_icon' => $icon . 'd',
            'clouds' => $this->faker->numberBetween(0, 100),
            'visibility' => 10000,
            'sunrise' => $measuredAt->copy()->setTime(6, 30),
            'sunset' => $measuredAt->copy()->setTime(16, 30),
            'measured_at' => $measuredAt,
            'data_json' => null,
        ];
    }

    // Czas pomiaru (oraz wschód/zachód słońca tego dnia)
    public function at(Carbon|string $measuredAt): static
    {
        $measuredAt = Carbon::parse($measuredAt);

        return $this->state(fn () => [
            'measured_at' => $measuredAt,
            'sunrise' => $measuredAt->copy()->setTime(6, 30),
            'sunset' => $measuredAt->copy()->setTime(16, 30),
        ]);
    }
}
