<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeatherData extends Model
{
    use HasFactory;

    protected $fillable = [
        'city_id',
        'temperature',
        'feels_like',
        'humidity',
        'pressure',
        'wind_speed',
        'wind_direction',
        'weather_condition',
        'weather_description',
        'weather_icon',
        'clouds',
        'visibility',
        'sunrise',
        'sunset',
        'measured_at',
        'data_json',
    ];

    protected $casts = [
        'temperature' => 'float',
        'feels_like' => 'float',
        'humidity' => 'integer',
        'pressure' => 'integer',
        'wind_speed' => 'float',
        'wind_direction' => 'integer',
        'clouds' => 'integer',
        'visibility' => 'integer',
        'sunrise' => 'datetime',
        'sunset' => 'datetime',
        'measured_at' => 'datetime',
        'data_json' => 'array',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}