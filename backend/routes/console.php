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
Schedule::command('air:update')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/air-update.log'));
