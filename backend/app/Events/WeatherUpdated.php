<?php

namespace App\Events;

use App\Models\City;
use App\Models\WeatherData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Wysyłane, gdy dla miasta zapisano NOWY pomiar (nowy wiersz w weather_data).
 */
class WeatherUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public City $city,
        public WeatherData $weatherData,
    ) {
    }
}
