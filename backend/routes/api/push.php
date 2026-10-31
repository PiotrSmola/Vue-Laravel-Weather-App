<?php

use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

// Web Push: klucz publiczny VAPID (publiczny) i subskrypcje urządzeń zalogowanego użytkownika
Route::get('/push/public-key', [PushSubscriptionController::class, 'publicKey']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/push/subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('/push/subscriptions', [PushSubscriptionController::class, 'destroy']);
    Route::post('/push/test', [PushSubscriptionController::class, 'test'])->middleware('throttle:5,1');
});
