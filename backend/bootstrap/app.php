<?php

use App\Exceptions\WeatherApiException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // CORS obsługuje wbudowany HandleCors (konfiguracja: config/cors.php)
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Błędy API OpenWeatherMap: szczegóły są w logach, klient dostaje ogólny komunikat
        $exceptions->dontReport(WeatherApiException::class);

        $exceptions->render(function (WeatherApiException $e, Request $request) {
            // Wyczerpany limit zapytań (nasz licznik lub HTTP 429 z API)
            if ($e->status === 429) {
                return response()->json([
                    'error' => 'Przekroczono limit zapytań do serwisu pogodowego. Spróbuj za chwilę.',
                ], 503);
            }

            if ($e->isNotFound()) {
                return response()->json(['error' => 'Nie znaleziono miasta'], 404);
            }

            return response()->json(['error' => 'Nie udało się pobrać danych pogodowych'], 502);
        });
    })->create();
