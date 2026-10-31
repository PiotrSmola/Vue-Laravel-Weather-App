<?php

use App\Http\Controllers\FavoriteCityController;
use Illuminate\Support\Facades\Route;

// Ulubione miasta: kolejność i własne nazwy (aliasy)
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/cities/user/order', [FavoriteCityController::class, 'reorder']);
    Route::patch('/cities/user/{cityId}', [FavoriteCityController::class, 'update'])->whereNumber('cityId');
});
