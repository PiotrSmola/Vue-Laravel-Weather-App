<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\WeatherData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WeatherController extends Controller
{
    private $weatherCacheMinutes = 15;
    private $forecastCacheMinutes = 30;
    
    public function getCurrentWeather($cityId)
    {
        try {
            Log::info('Pobieranie pogody dla miasta ID: ' . $cityId);
            
            $city = City::where('openweather_id', $cityId)->first();
            
            if (!$city) {
                try {
                    $response = Http::timeout(1)->get('https://api.openweathermap.org/data/2.5/weather', [
                        'id' => $cityId,
                        'appid' => config('weather.api_key'),
                        'units' => 'metric'
                    ]);
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        
                        $city = City::create([
                            'openweather_id' => $cityId,
                            'name' => $data['name'],
                            'country' => $data['sys']['country'],
                            'latitude' => $data['coord']['lat'],
                            'longitude' => $data['coord']['lon'],
                        ]);
                        
                        Log::info('Dodano nowe miasto do bazy: ' . $data['name']);
                    } else {
                        Log::warning('Nie znaleziono miasta o ID: ' . $cityId);
                        return response()->json(['error' => 'Nie znaleziono miasta'], 404);
                    }
                } catch (\Exception $e) {
                    Log::error('Błąd podczas dodawania miasta: ' . $e->getMessage());
                    return response()->json(['error' => 'Nie znaleziono miasta'], 404);
                }
            }
            
            $cacheKey = 'weather_' . $cityId;
            
            if (Cache::has($cacheKey)) {
                Log::info('Zwracanie pogody z cache dla miasta: ' . $city->name);
                return Cache::get($cacheKey);
            }

            $latestWeather = $city->weatherData()->latest()->first();

            if ($latestWeather && $latestWeather->created_at->diffInMinutes(now()) < config('weather.update_interval', $this->weatherCacheMinutes)) {
                Log::info('Zwracanie najnowszych danych z bazy dla miasta: ' . $city->name);
                $weatherData = $latestWeather->data_json;
                Cache::put($cacheKey, $weatherData, now()->addMinutes(5));
                return $weatherData;
            }

            Log::info('Pobieranie nowych danych z API dla miasta: ' . $city->name);
            
            $response = Http::timeout(1)->get('https://api.openweathermap.org/data/2.5/weather', [
                'id' => $cityId,
                'appid' => config('weather.api_key'),
                'units' => 'metric',
                'lang' => 'pl'
            ]);
            
            if (!$response->successful()) {
                Log::error('Błąd API OpenWeatherMap: ' . $response->body());
                return response()->json([
                    'error' => 'Nie udało się pobrać danych pogodowych',
                    'details' => $response->body()
                ], 500);
            }
            
            $weatherData = $response->json();

            dispatch(function () use ($city, $weatherData) {
                $this->saveWeatherData($city, $weatherData);
            })->afterResponse();
            
            Cache::put($cacheKey, $weatherData, now()->addMinutes(5));
            
            return $weatherData;
        } catch (\Exception $e) {
            Log::error('Wyjątek podczas pobierania pogody: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'error' => 'Wystąpił błąd: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getWeatherByCoordinates(Request $request)
    {
        try {
            $request->validate([
                'lat' => 'required|numeric',
                'lon' => 'required|numeric',
            ]);
            
            $lat = $request->lat;
            $lon = $request->lon;
            $lang = $request->get('lang', 'pl');
            
            Log::info("Pobieranie pogody dla współrzędnych: lat=$lat, lon=$lon");
            
            $cacheKey = "weather_coords_{$lat}_{$lon}";
            
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            $response = Http::timeout(1)->get('https://api.openweathermap.org/data/2.5/weather', [
                'lat' => $lat,
                'lon' => $lon,
                'appid' => config('weather.api_key'),
                'units' => 'metric',
                'lang' => $lang
            ]);
            
            if (!$response->successful()) {
                Log::error('Błąd API OpenWeatherMap: ' . $response->body());
                return response()->json([
                    'error' => 'Nie udało się pobrać danych pogodowych',
                    'details' => $response->body()
                ], 500);
            }
            
            $weatherData = $response->json();
            
            $city = City::where('openweather_id', $weatherData['id'])->first();
            
            if (!$city) {
                $city = City::create([
                    'openweather_id' => $weatherData['id'],
                    'name' => $weatherData['name'],
                    'country' => $weatherData['sys']['country'],
                    'latitude' => $weatherData['coord']['lat'],
                    'longitude' => $weatherData['coord']['lon'],
                ]);
                
                Log::info('Dodano nowe miasto do bazy: ' . $weatherData['name']);
            }
            
            dispatch(function () use ($city, $weatherData) {
                $this->saveWeatherData($city, $weatherData);
            })->afterResponse();
            
            Cache::put($cacheKey, $weatherData, now()->addMinutes($this->weatherCacheMinutes));
            
            return $weatherData;
        } catch (\Exception $e) {
            Log::error('Wyjątek podczas pobierania pogody dla współrzędnych: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Wystąpił błąd: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getWeatherByCity(Request $request)
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2',
            ]);
            
            $query = $request->q;
            $lang = $request->get('lang', 'pl');
            
            Log::info("Pobieranie pogody dla miasta: $query");
            
            $cacheKey = "weather_query_" . md5($query);
            
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            $response = Http::timeout(1)->get('https://api.openweathermap.org/data/2.5/weather', [
                'q' => $query,
                'appid' => config('weather.api_key'),
                'units' => 'metric',
                'lang' => $lang
            ]);
            
            if (!$response->successful()) {
                Log::error('Błąd API OpenWeatherMap: ' . $response->body());
                return response()->json([
                    'error' => 'Nie udało się pobrać danych pogodowych',
                    'details' => $response->body()
                ], 500);
            }
            
            $weatherData = $response->json();
            
            $city = City::where('openweather_id', $weatherData['id'])->first();
            
            if (!$city) {
                $city = City::create([
                    'openweather_id' => $weatherData['id'],
                    'name' => $weatherData['name'],
                    'country' => $weatherData['sys']['country'],
                    'latitude' => $weatherData['coord']['lat'],
                    'longitude' => $weatherData['coord']['lon'],
                ]);
                
                Log::info('Dodano nowe miasto do bazy: ' . $weatherData['name']);
            }
            
            dispatch(function () use ($city, $weatherData) {
                $this->saveWeatherData($city, $weatherData);
            })->afterResponse();
            
            Cache::put($cacheKey, $weatherData, now()->addMinutes($this->weatherCacheMinutes));
            
            return $weatherData;
        } catch (\Exception $e) {
            Log::error('Wyjątek podczas pobierania pogody dla miasta: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Wystąpił błąd: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getForecast($cityId)
    {
        try {
            $city = City::where('openweather_id', $cityId)->first();
            
            if (!$city) {
                return response()->json(['error' => 'Nie znaleziono miasta'], 404);
            }
            
            $cacheKey = 'forecast_' . $cityId;
            
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            $response = Http::timeout(1)->get('https://api.openweathermap.org/data/2.5/forecast', [
                'id' => $cityId,
                'appid' => config('weather.api_key'),
                'units' => 'metric',
                'lang' => 'pl'
            ]);
            
            if (!$response->successful()) {
                Log::error('Błąd API OpenWeatherMap (prognoza): ' . $response->body());
                return response()->json([
                    'error' => 'Nie udało się pobrać prognozy pogody',
                    'details' => $response->body()
                ], 500);
            }
            
            $forecastData = $response->json();
            
            Cache::put($cacheKey, $forecastData, now()->addMinutes($this->forecastCacheMinutes));
            
            return $forecastData;
        } catch (\Exception $e) {
            Log::error('Wyjątek podczas pobierania prognozy: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Wystąpił błąd: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getForecastByCoordinates(Request $request)
    {
        try {
            $request->validate([
                'lat' => 'required|numeric',
                'lon' => 'required|numeric',
            ]);
            
            $lat = $request->lat;
            $lon = $request->lon;
            $lang = $request->get('lang', 'pl');
            
            Log::info("Pobieranie prognozy dla współrzędnych: lat=$lat, lon=$lon");
            
            $cacheKey = "forecast_coords_{$lat}_{$lon}";
            
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            $response = Http::timeout(1)->get('https://api.openweathermap.org/data/2.5/forecast', [
                'lat' => $lat,
                'lon' => $lon,
                'appid' => config('weather.api_key'),
                'units' => 'metric',
                'lang' => $lang
            ]);
            
            if (!$response->successful()) {
                Log::error('Błąd API OpenWeatherMap (prognoza): ' . $response->body());
                return response()->json([
                    'error' => 'Nie udało się pobrać prognozy pogody',
                    'details' => $response->body()
                ], 500);
            }
            
            $forecastData = $response->json();
            
            Cache::put($cacheKey, $forecastData, now()->addMinutes($this->forecastCacheMinutes));
            
            return $forecastData;
        } catch (\Exception $e) {
            Log::error('Wyjątek podczas pobierania prognozy dla współrzędnych: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Wystąpił błąd: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getForecastByCity(Request $request)
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2',
            ]);
            
            $query = $request->q;
            $lang = $request->get('lang', 'pl');
            
            Log::info("Pobieranie prognozy dla miasta: $query");
            
            $cacheKey = "forecast_query_" . md5($query);
            
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            $response = Http::timeout(1)->get('https://api.openweathermap.org/data/2.5/forecast', [
                'q' => $query,
                'appid' => config('weather.api_key'),
                'units' => 'metric',
                'lang' => $lang
            ]);
            
            if (!$response->successful()) {
                Log::error('Błąd API OpenWeatherMap (prognoza): ' . $response->body());
                return response()->json([
                    'error' => 'Nie udało się pobrać prognozy pogody',
                    'details' => $response->body()
                ], 500);
            }
            
            $forecastData = $response->json();
            
            Cache::put($cacheKey, $forecastData, now()->addMinutes($this->forecastCacheMinutes));
            
            return $forecastData;
        } catch (\Exception $e) {
            Log::error('Wyjątek podczas pobierania prognozy dla miasta: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Wystąpił błąd: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function saveWeatherData(City $city, array $weatherData)
    {
        try {
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
            
            Log::info('Zapisano dane pogodowe dla miasta: ' . $city->name);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Błąd podczas zapisywania danych pogodowych: ' . $e->getMessage());
            return false;
        }
    }
}