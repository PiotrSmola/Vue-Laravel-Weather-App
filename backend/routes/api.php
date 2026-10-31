<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;


Route::get('/user', [UserController::class, 'show'])->middleware('auth:sanctum');


Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
});

// Trasy statyczne przed trasami z parametrem {cityId}
Route::get('/weather/coordinates', [WeatherController::class, 'getWeatherByCoordinates']);
Route::get('/weather/city', [WeatherController::class, 'getWeatherByCity']);
Route::get('/weather/forecast/coordinates', [WeatherController::class, 'getForecastByCoordinates']);
Route::get('/weather/forecast/city', [WeatherController::class, 'getForecastByCity']);

Route::get('/weather/forecast/{cityId}', [WeatherController::class, 'getForecast'])->whereNumber('cityId');
Route::get('/weather/{cityId}', [WeatherController::class, 'getCurrentWeather'])->whereNumber('cityId');

Route::get('/cities', [CityController::class, 'index']);
Route::get('/cities/search', [CityController::class, 'searchCities']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cities/user', [CityController::class, 'getUserCities']);
    Route::post('/cities', [CityController::class, 'addCity']);
    Route::delete('/cities/{cityId}', [CityController::class, 'removeCity'])->whereNumber('cityId');
    Route::get('/cities/{cityId}/historical', [CityController::class, 'getHistoricalData'])->whereNumber('cityId');
});

/*
|--------------------------------------------------------------------------
| Trasy funkcji (routes/api/*.php)
|--------------------------------------------------------------------------
| Każda funkcja dodaje własny plik routes/api/<funkcja>.php - jest on ładowany
| automatycznie (alfabetycznie), z prefiksem /api i grupą middleware "api".
*/
$featureRoutes = glob(__DIR__ . '/api/*.php') ?: [];
sort($featureRoutes);

foreach ($featureRoutes as $featureRoutesFile) {
    require $featureRoutesFile;
}
