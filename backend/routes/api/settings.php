<?php

use App\Http\Controllers\UserSettingsController;
use Illuminate\Support\Facades\Route;

// Ustawienia użytkownika (jednostki, jednostka wiatru, język, miasto domowe)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/settings', [UserSettingsController::class, 'show']);
    Route::put('/user/settings', [UserSettingsController::class, 'update']);
});
