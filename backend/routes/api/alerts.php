<?php

use App\Http\Controllers\AlertEventController;
use App\Http\Controllers\AlertRuleController;
use Illuminate\Support\Facades\Route;

// Alerty pogodowe: reguły użytkownika i lista wyzwolonych alertów (powiadomienia w aplikacji)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/alerts', [AlertRuleController::class, 'index']);
    Route::get('/alerts/meta', [AlertRuleController::class, 'meta']);
    Route::post('/alerts', [AlertRuleController::class, 'store']);

    Route::get('/alerts/events', [AlertEventController::class, 'index']);
    Route::get('/alerts/events/unread-count', [AlertEventController::class, 'unreadCount']);
    Route::post('/alerts/events/read-all', [AlertEventController::class, 'markAllRead']);
    Route::post('/alerts/events/{id}/read', [AlertEventController::class, 'markRead'])->whereNumber('id');

    Route::match(['put', 'patch'], '/alerts/{id}', [AlertRuleController::class, 'update'])->whereNumber('id');
    Route::delete('/alerts/{id}', [AlertRuleController::class, 'destroy'])->whereNumber('id');
});
