<?php

use App\Http\Controllers\AirQualityController;
use Illuminate\Support\Facades\Route;

// Jakość powietrza (OpenWeather Air Pollution API). Trasa statyczna przed trasami z {cityId}.
Route::get('/air/coordinates', [AirQualityController::class, 'coordinates']);
Route::get('/air/{cityId}/forecast', [AirQualityController::class, 'forecast'])->whereNumber('cityId');
Route::get('/air/{cityId}', [AirQualityController::class, 'current'])->whereNumber('cityId');

// Historia - tylko dla zalogowanych i miast w ulubionych
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/air/{cityId}/historical', [AirQualityController::class, 'historical'])->whereNumber('cityId');
});
