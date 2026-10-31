<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

// Przypomnienie i reset hasła (dla gości)
Route::middleware('throttle:password-reset')->group(function () {
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink']);
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);
});

// Nazwana trasa używana do podpisywania linków weryfikacyjnych (przekierowuje na frontend)
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'redirectToFrontend'])
    ->whereNumber('id')
    ->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {
    // Weryfikacja adresu e-mail
    Route::post('/email/verify', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:verification-check');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:verification');

    // Konto
    Route::get('/account', [AccountController::class, 'show']);
    Route::delete('/account', [AccountController::class, 'destroy']);
    Route::put('/account/profile', [AccountController::class, 'updateProfile']);
    Route::put('/account/password', [AccountController::class, 'updatePassword']);
    Route::get('/account/export', [AccountController::class, 'export']);
    Route::get('/account/tokens', [AccountController::class, 'tokens']);
    Route::delete('/account/tokens/{tokenId}', [AccountController::class, 'destroyToken'])->whereNumber('tokenId');
});
