<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'openweather_id',
        'name',
        'country',
        'latitude',
        'longitude',
    ];

    public function weatherData()
    {
        return $this->hasMany(WeatherData::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_cities');
    }

    public function currentWeather()
    {
        return $this->hasOne(WeatherData::class)->latest();
    }
}