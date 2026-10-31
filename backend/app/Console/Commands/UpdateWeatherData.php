<?php

namespace App\Console\Commands;

use App\Exceptions\WeatherApiException;
use App\Services\OpenWeatherClient;
use App\Services\WeatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

class UpdateWeatherData extends Command
{
    // php artisan schedule:work

    protected $signature = 'weather:update';

    protected $description = 'Aktualizuje dane pogodowe dla miast w ulubionych oraz miast domyślnych';

    public function handle(WeatherService $weather, OpenWeatherClient $client)
    {
        // Suma: wszystkie miasta w ulubionych + miasta domyślne z config/weather.php
        $openweatherIds = $weather->openweatherIdsToUpdate();

        if ($openweatherIds->isEmpty()) {
            $this->info('Brak miast do aktualizacji.');
            return Command::SUCCESS;
        }

        $this->info('Aktualizacja danych pogodowych dla ' . $openweatherIds->count() . ' miast...');

        $successCount = 0;
        $failCount = 0;
        $delay = (int) config('weather.update_delay_ms', 1000);

        foreach ($openweatherIds as $index => $openweatherId) {
            if ($index > 0 && $delay > 0) {
                Sleep::for($delay)->milliseconds();
            }

            try {
                $payload = $client->current(['id' => $openweatherId], config('weather.default_lang', 'pl'));
                $city = $weather->cityFromPayload($payload);
                $weather->storeMeasurement($city, $payload);

                $successCount++;
                $this->info('Zaktualizowano dane dla miasta: ' . $city->name);
            } catch (WeatherApiException $e) {
                $failCount++;
                $this->error('Nie udało się pobrać danych dla miasta o ID: ' . $openweatherId);
            } catch (\Throwable $e) {
                $failCount++;
                $this->error('Wystąpił błąd dla miasta o ID: ' . $openweatherId);
                Log::error('Wyjątek podczas aktualizacji pogody dla miasta ' . $openweatherId . ': ' . $e->getMessage());
            }
        }

        $this->info('Zakończono aktualizację danych.');
        $this->info('Sukces: ' . $successCount . ' miast, Błędy: ' . $failCount . ' miast');

        return Command::SUCCESS;
    }
}
