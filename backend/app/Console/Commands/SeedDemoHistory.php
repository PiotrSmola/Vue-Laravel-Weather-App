<?php

namespace App\Console\Commands;

use App\Exceptions\WeatherApiException;
use App\Models\User;
use App\Services\WeatherService;
use Database\Seeders\HistoryDemoSeeder;
use Illuminate\Console\Command;

/**
 * Wypełnia bazę demonstracyjną historią pomiarów (dane SZTUCZNE - tylko do podglądu wykresów).
 *
 *   php artisan weather:demo-history 759734 --days=60
 *   php artisan weather:demo-history 3094802 --days=90 --interval=30 --user=jan@example.com
 *
 * Miasto, którego nie ma w bazie, jest pobierane z API (jedno zapytanie).
 * --user dodaje miasto do ulubionych użytkownika, aby historia była dla niego dostępna.
 */
class SeedDemoHistory extends Command
{
    protected $signature = 'weather:demo-history
        {cityId=759734 : ID miasta w OpenWeather}
        {--days=60 : Liczba dni wstecz (1-366)}
        {--interval=60 : Odstęp między pomiarami w minutach (10-240)}
        {--user= : E-mail użytkownika, któremu dodać miasto do ulubionych}
        {--seed= : Ziarno generatora (powtarzalne dane)}
        {--force : Pozwól uruchomić w środowisku produkcyjnym}';

    protected $description = 'Generuje demonstracyjną (sztuczną) historię pomiarów dla miasta';

    public function handle(WeatherService $weather)
    {
        if (app()->isProduction() && !$this->option('force')) {
            $this->error('Środowisko produkcyjne - dane demonstracyjne wymagają opcji --force.');
            return Command::FAILURE;
        }

        $days = (int) $this->option('days');
        $interval = (int) $this->option('interval');

        if ($days < 1 || $days > 366 || $interval < 10 || $interval > 240) {
            $this->error('Nieprawidłowe opcje: --days 1-366, --interval 10-240.');
            return Command::INVALID;
        }

        try {
            $city = $weather->findOrCreateCity((int) $this->argument('cityId'));
        } catch (WeatherApiException $e) {
            $this->error('Nie udało się pobrać miasta o ID: ' . $this->argument('cityId'));
            return Command::FAILURE;
        }

        if ($email = $this->option('user')) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $this->error('Nie znaleziono użytkownika: ' . $email);
                return Command::FAILURE;
            }

            $user->cities()->syncWithoutDetaching([$city->id]);
            $this->info('Miasto ' . $city->name . ' jest w ulubionych użytkownika ' . $email . '.');
        }

        $seed = $this->option('seed') !== null ? (int) $this->option('seed') : null;
        $inserted = HistoryDemoSeeder::generate($city, $days, $interval, $seed);

        $this->info("Dodano {$inserted} pomiarów demonstracyjnych dla miasta {$city->name} ({$days} dni, co {$interval} min).");

        return Command::SUCCESS;
    }
}
