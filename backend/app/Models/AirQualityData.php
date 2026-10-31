<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pomiar jakości powietrza dla miasta (aqi w skali OpenWeather 1-5, składniki w µg/m³).
 */
class AirQualityData extends Model
{
    protected $table = 'air_quality_data';

    // Składniki zwracane przez OpenWeather Air Pollution API
    public const COMPONENTS = ['co', 'no', 'no2', 'o3', 'so2', 'pm2_5', 'pm10', 'nh3'];

    protected $fillable = [
        'city_id',
        'aqi',
        'co',
        'no',
        'no2',
        'o3',
        'so2',
        'pm2_5',
        'pm10',
        'nh3',
        'measured_at',
    ];

    protected $casts = [
        'aqi' => 'integer',
        'co' => 'float',
        'no' => 'float',
        'no2' => 'float',
        'o3' => 'float',
        'so2' => 'float',
        'pm2_5' => 'float',
        'pm10' => 'float',
        'nh3' => 'float',
        'measured_at' => 'datetime',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
