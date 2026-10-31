<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

// Zarządzanie kontem: limity żądań dla resetu hasła i ponownej wysyłki maila weryfikacyjnego
class AccountServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $tooMany = function (Request $request, array $headers) {
            return response()->json([
                'message' => 'Zbyt wiele prób. Spróbuj ponownie za chwilę.',
            ], 429, $headers);
        };

        // Przypomnienie i reset hasła: 5 żądań na minutę na adres IP
        RateLimiter::for('password-reset', function (Request $request) use ($tooMany) {
            return Limit::perMinute(5)->by('password-reset|' . $request->ip())->response($tooMany);
        });

        // Ponowna wysyłka maila weryfikacyjnego: 3 na minutę na użytkownika
        RateLimiter::for('verification', function (Request $request) use ($tooMany) {
            return Limit::perMinute(3)
                ->by('verification|' . ($request->user()?->getKey() ?? $request->ip()))
                ->response($tooMany);
        });

        // Weryfikacja linku: 10 prób na minutę na użytkownika
        RateLimiter::for('verification-check', function (Request $request) use ($tooMany) {
            return Limit::perMinute(10)
                ->by('verification-check|' . ($request->user()?->getKey() ?? $request->ip()))
                ->response($tooMany);
        });
    }
}
