<?php

namespace App\Console\Commands;

use App\Exceptions\WeatherApiException;
use App\Services\AirQualityService;
use App\Services\WeatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

class UpdateAirQualityData extends Command
{
    protected $signature = 'air:update';

    protected $description = 'Aktualizuje dane o jakości powietrza dla miast w ulubionych oraz miast domyślnych';

    public function handle(WeatherService $weather, AirQualityService $air)
    {
        // Te same miasta co w weather:update (brakujące miasta domyślne są dodawane do bazy)
        $cities = $weather->ensureCities($weather->openweatherIdsToUpdate()->all());

        if ($cities->isEmpty()) {
            $this->info('Brak miast do aktualizacji.');
            return Command::SUCCESS;
        }

        $this->info('Aktualizacja danych o jakości powietrza dla ' . $cities->count() . ' miast...');

        $successCount = 0;
        $failCount = 0;
        $delay = (int) config('weather.update_delay_ms', 1500);

        foreach ($cities as $index => $city) {
            if ($index > 0 && $delay > 0) {
                Sleep::for($delay)->milliseconds();
            }

            try {
                $air->refresh($city);

                $successCount++;
                $this->info('Zaktualizowano jakość powietrza dla miasta: ' . $city->name);
            } catch (WeatherApiException $e) {
                $failCount++;
                $this->error('Nie udało się pobrać jakości powietrza dla miasta: ' . $city->name);
            } catch (\Throwable $e) {
                $failCount++;
                $this->error('Wystąpił błąd dla miasta: ' . $city->name);
                Log::error('Wyjątek podczas aktualizacji jakości powietrza dla miasta ' . $city->openweather_id . ': ' . $e->getMessage());
            }
        }

        $this->info('Zakończono aktualizację danych o jakości powietrza.');
        $this->info('Sukces: ' . $successCount . ' miast, Błędy: ' . $failCount . ' miast');

        return Command::SUCCESS;
    }
}
