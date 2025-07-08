<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\WeatherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/weather/{cityId}', [WeatherController::class, 'getCurrentWeather']);
Route::get('/weather/forecast/{cityId}', [WeatherController::class, 'getForecast']);

Route::get('/weather/coordinates', [WeatherController::class, 'getWeatherByCoordinates']);
Route::get('/weather/city', [WeatherController::class, 'getWeatherByCity']);
Route::get('/weather/forecast/coordinates', [WeatherController::class, 'getForecastByCoordinates']);
Route::get('/weather/forecast/city', [WeatherController::class, 'getForecastByCity']);

Route::get('/cities', [CityController::class, 'index']);
Route::get('/cities/search', [CityController::class, 'searchCities']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cities/user', [CityController::class, 'getUserCities']);
    Route::post('/cities', [CityController::class, 'addCity']);
    Route::delete('/cities/{cityId}', [CityController::class, 'removeCity']);
    Route::get('/cities/{cityId}/historical', [CityController::class, 'getHistoricalData']);
});