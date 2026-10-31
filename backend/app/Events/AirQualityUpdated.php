<?php

namespace App\Events;

use App\Models\AirQualityData;
use App\Models\City;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Wysyłane, gdy dla miasta zapisano NOWY pomiar jakości powietrza (nowy wiersz w air_quality_data).
 */
class AirQualityUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public City $city,
        public AirQualityData $airQuality,
    ) {
    }
}
