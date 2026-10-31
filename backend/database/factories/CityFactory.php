<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        return [
            'openweather_id' => $this->faker->unique()->numberBetween(100000, 9999999),
            'name' => $this->faker->city(),
            'country' => 'PL',
            'latitude' => $this->faker->latitude(49, 55),
            'longitude' => $this->faker->longitude(14, 24),
        ];
    }
}
