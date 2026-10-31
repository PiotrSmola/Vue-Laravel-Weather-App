<?php

namespace App\Providers;

use App\Services\OpenWeatherClient;
use App\Services\WeatherService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->app->singleton(OpenWeatherClient::class, fn () => OpenWeatherClient::fromConfig());
        $this->app->singleton(WeatherService::class);
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Harmonogram zadań znajduje się w routes/console.php

        // Logowanie i rejestracja: 5 prób na minutę na parę e-mail + IP
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)
                ->by(mb_strtolower((string) $request->input('email')) . '|' . $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Zbyt wiele prób. Spróbuj ponownie za chwilę.',
                    ], 429, $headers);
                });
        });
    }
}
