<?php

use App\Http\Controllers\CompareController;
use App\Http\Controllers\HistoryController;
use Illuminate\Support\Facades\Route;

// Rozszerzona historia ulubionego miasta: agregaty dzienne, podsumowanie, eksport CSV
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cities/{cityId}/history/daily', [HistoryController::class, 'daily'])->whereNumber('cityId');
    Route::get('/cities/{cityId}/history/summary', [HistoryController::class, 'summary'])->whereNumber('cityId');
    Route::get('/cities/{cityId}/history/export', [HistoryController::class, 'export'])->whereNumber('cityId');
});

// Porównanie 2-4 miast (publiczne; limit zapytań, bo może odpytywać API OpenWeather)
Route::get('/compare', [CompareController::class, 'index'])->middleware('throttle:30,1');
