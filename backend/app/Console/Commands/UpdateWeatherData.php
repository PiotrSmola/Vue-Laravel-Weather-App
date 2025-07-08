<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\WeatherData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UpdateWeatherData extends Command
{
    // php artisan schedule:work
    
    protected $signature = 'weather:update';

    protected $description = 'Aktualizuje dane pogodowe dla miast w ulubionych';

    public function handle()
    {
        $cities = City::whereIn('id', function($query) {
            $query->select('city_id')->from('user_cities')->distinct();
        })->get();
        
        if ($cities->isEmpty()) {
            $this->info('Brak miast w ulubionych. Ładowanie domyślnych 10 polskich miast');
            $this->loadDefaultCities();
            
            $cities = City::whereIn('id', function($query) {
                $query->select('city_id')->from('user_cities')->distinct();
            })->get();
            
            if ($cities->isEmpty()) {
                $this->info('Brak miast w ulubionych. Nie ma danych do aktualizacji.');
                return Command::SUCCESS;
            }
        }
        
        $this->info('Aktualizacja danych pogodowych dla ' . $cities->count() . ' miast...');
        
        $successCount = 0;
        $failCount = 0;
        
        foreach ($cities as $city) {
            try {
                $response = Http::get(config('weather.base_url') . 'weather', [
                    'id' => $city->openweather_id,
                    'appid' => config('weather.api_key'),
                    'units' => 'metric',
                    'lang' => 'pl'
                ]);
                
                if ($response->successful()) {
                    $weatherData = $response->json();
                    
                    WeatherData::create([
                        'city_id' => $city->id,
                        'temperature' => $weatherData['main']['temp'],
                        'feels_like' => $weatherData['main']['feels_like'],
                        'humidity' => $weatherData['main']['humidity'],
                        'pressure' => $weatherData['main']['pressure'],
                        'wind_speed' => $weatherData['wind']['speed'],
                        'wind_direction' => $weatherData['wind']['deg'],
                        'weather_condition' => $weatherData['weather'][0]['main'],
                        'weather_description' => $weatherData['weather'][0]['description'],
                        'weather_icon' => $weatherData['weather'][0]['icon'],
                        'clouds' => $weatherData['clouds']['all'],
                        'visibility' => $weatherData['visibility'],
                        'sunrise' => date('Y-m-d H:i:s', $weatherData['sys']['sunrise']),
                        'sunset' => date('Y-m-d H:i:s', $weatherData['sys']['sunset']),
                        'measured_at' => date('Y-m-d H:i:s', $weatherData['dt']),
                        'data_json' => $weatherData,
                    ]);
                    
                    $successCount++;
                    $this->info('Zaktualizowano dane dla miasta: ' . $city->name);
                } else {
                    $failCount++;
                    $this->error('Nie udało się pobrać danych dla miasta: ' . $city->name);
                    Log::error('Błąd aktualizacji pogody dla miasta ' . $city->name . ': ' . $response->body());
                }
            } catch (\Exception $e) {
                $failCount++;
                $this->error('Wystąpił błąd dla miasta ' . $city->name . ': ' . $e->getMessage());
                Log::error('Wyjątek podczas aktualizacji pogody dla miasta ' . $city->name . ': ' . $e->getMessage());
            }
            
            sleep(1);
        }
        
        $this->info('Zakończono aktualizację danych.');
        $this->info('Sukces: ' . $successCount . ' miast, Błędy: ' . $failCount . ' miast');
        
        return Command::SUCCESS;
    }
    
    private function loadDefaultCities()
    {
        $defaultCities = config('weather.default_cities');
        
        foreach ($defaultCities as $cityId) {
            if (City::where('openweather_id', $cityId)->exists()) {
                continue;
            }
            
            $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                'id' => $cityId,
                'appid' => config('weather.api_key'),
                'units' => 'metric'
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                City::create([
                    'openweather_id' => $cityId,
                    'name' => $data['name'],
                    'country' => $data['sys']['country'],
                    'latitude' => $data['coord']['lat'],
                    'longitude' => $data['coord']['lon'],
                ]);
                
                $this->info('Dodano miasto: ' . $data['name']);
            } else {
                $this->error('Nie udało się dodać miasta o ID: ' . $cityId);
                Log::error('Błąd dodawania miasta o ID ' . $cityId . ': ' . $response->body());
            }
            
            sleep(1);
        }
    }
}