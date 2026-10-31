<?php

namespace Tests\Concerns;

use App\Models\AlertRule;
use App\Models\City;
use App\Models\User;
use App\Models\WeatherData;
use Tests\Fixtures\OpenWeatherFixtures;

/**
 * Pomocnicze metody do testów alertów: miasta, ulubione, reguły i pomiary.
 */
trait CreatesAlerts
{
    protected function makeCity(int $openweatherId = 759734): City
    {
        [$name, $lat, $lon] = OpenWeatherFixtures::CITIES[$openweatherId] ?? ['Miasto ' . $openweatherId, 50.0, 20.0];

        return City::firstOrCreate(['openweather_id' => $openweatherId], [
            'name' => $name,
            'country' => 'PL',
            'latitude' => $lat,
            'longitude' => $lon,
        ]);
    }

    // Użytkownik z miastem w ulubionych
    protected function userWithFavorite(int $openweatherId = 759734, array $attributes = []): array
    {
        $user = User::factory()->create($attributes);
        $city = $this->makeCity($openweatherId);
        $user->cities()->attach($city->id);

        return [$user, $city];
    }

    protected function makeRule(User $user, City $city, array $attributes = []): AlertRule
    {
        return AlertRule::create(array_merge([
            'user_id' => $user->id,
            'city_id' => $city->id,
            'type' => 'temp_below',
            'threshold' => 0,
            'channels' => ['mail'],
            'active' => true,
            'cooldown_minutes' => 360,
        ], $attributes));
    }

    protected function makeMeasurement(City $city, array $attributes = []): WeatherData
    {
        static $offset = 0;
        $offset++;

        return WeatherData::create(array_merge([
            'city_id' => $city->id,
            'temperature' => 10,
            'feels_like' => 9,
            'humidity' => 60,
            'pressure' => 1015,
            'wind_speed' => 3,
            'wind_direction' => 200,
            'weather_condition' => 'Clear',
            'weather_description' => 'bezchmurnie',
            'weather_icon' => '01d',
            'clouds' => 0,
            'visibility' => 10000,
            'sunrise' => now(),
            'sunset' => now(),
            'measured_at' => now()->subSeconds($offset),
        ], $attributes));
    }
}
