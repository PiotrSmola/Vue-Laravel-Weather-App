<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/*
|--------------------------------------------------------------------------
| Harmonogram zadań (php artisan schedule:work - kontener "scheduler")
|--------------------------------------------------------------------------
| Kolejne zadania dopisuj poniżej, każde jako osobne Schedule::...
*/

// Aktualizacja pogody dla ulubionych i domyślnych miast co 30 minut
Schedule::command('weather:update')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/weather-update.log'));

// Aktualizacja jakości powietrza dla ulubionych i domyślnych miast co godzinę
// (w 15. minucie, żeby nie nakładała się na weather:update i nie przekroczyła limitu API)
Schedule::command('air:update')
    ->hourlyAt(15)
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/air-update.log'));

// Alerty prognozy (deszcz jutro, przymrozek) dla miast z aktywnymi regułami co 3 godziny
// (w 45. minucie, żeby nie nakładały się na weather:update i air:update)
Schedule::command('alerts:forecast')
    ->cron('45 */3 * * *')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/alerts-forecast.log'));
