<?php

use App\Http\Controllers\MapController;
use App\Http\Controllers\OneCallController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

// Limit kafelków na minutę na IP (config weather.map.tile_rate_limit, odczytywany przy każdym żądaniu)
RateLimiter::for('map-tiles', function (Request $request) {
    return Limit::perMinute(max(1, (int) config('weather.map.tile_rate_limit', 300)))
        ->by('map-tiles|' . $request->ip())
        ->response(fn (Request $request, array $headers) => response()->json([
            'error' => 'Zbyt wiele zapytań o kafelki mapy. Spróbuj ponownie za chwilę.',
        ], 429, $headers));
});

// Mapa pogodowa (publiczna): proxy kafelków OpenWeather i miasta z ostatnim pomiarem
Route::get('/map/tiles/{layer}/{z}/{x}/{y}.png', [MapController::class, 'tile'])
    ->where(['layer' => '[a-z_]+', 'z' => '[0-9]+', 'x' => '[0-9]+', 'y' => '[0-9]+'])
    ->middleware('throttle:map-tiles');

Route::get('/map/cities', [MapController::class, 'cities']);

// One Call API 3.0 (opcjonalne, flaga WEATHER_ONECALL_ENABLED) i lista włączonych funkcji
Route::get('/features', [OneCallController::class, 'features']);
Route::get('/weather/onecall/{cityId}', [OneCallController::class, 'show'])->whereNumber('cityId');
