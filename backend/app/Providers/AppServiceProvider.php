<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Console\Scheduling\Schedule;

class AppServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            
            // Komenda aktualizacji pogody co 30 minut
            $schedule->command('weather:update')
                    ->everyThirtyMinutes()
                    ->withoutOverlapping()
                    ->appendOutputTo(storage_path('logs/weather-update.log'));
        });
    }
}