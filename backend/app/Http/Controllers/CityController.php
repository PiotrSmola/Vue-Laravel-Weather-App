<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\WeatherData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CityController extends Controller
{
    public function index()
    {
        $userCityIds = DB::table('user_cities')->distinct()->pluck('city_id');
        
        $cities = City::whereNotIn('id', $userCityIds)->get();
        
        if ($cities->isEmpty()) {
            $this->loadDefaultCities();
            
            $userCityIds = DB::table('user_cities')->distinct()->pluck('city_id');
            $cities = City::whereNotIn('id', $userCityIds)->get();
        }
        
        return $cities;
    }

    public function getUserCities(Request $request)
    {
        $user = $request->user();
        $cities = $user->cities;

        $cities->each(function ($city) {
            $city->current_temp = optional($city->currentWeather)->temperature;
            $city->current_condition = optional($city->currentWeather)->weather_condition;
            $city->current_icon = optional($city->currentWeather)->weather_icon;
        });
        
        return $cities;
    }

    public function addCity(Request $request)
    {
        $request->validate([
            'city_id' => 'required|integer'
        ]);
        
        $cityId = $request->city_id;
        $user = $request->user();
        
        if ($user->cities()->count() >= 10) {
            return response()->json(['error' => 'Osiągnięto limit 10 ulubionych miast'], 400);
        }
        
        $city = City::where('openweather_id', $cityId)->first();
        
        if (!$city) {
            try {
                $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                    'id' => $cityId,
                    'appid' => config('weather.api_key'),
                    'units' => 'metric'
                ]);
                
                if (!$response->successful()) {
                    return response()->json(['error' => 'Nie znaleziono miasta'], 404);
                }
                
                $data = $response->json();
                
                $city = City::create([
                    'openweather_id' => $cityId,
                    'name' => $data['name'],
                    'country' => $data['sys']['country'],
                    'latitude' => $data['coord']['lat'],
                    'longitude' => $data['coord']['lon'],
                ]);
            } catch (\Exception $e) {
                Log::error('Błąd podczas dodawania miasta: ' . $e->getMessage());
                return response()->json(['error' => 'Wystąpił błąd podczas dodawania miasta'], 500);
            }
        }
        
        if ($user->cities()->where('city_id', $city->id)->exists()) {
            return response()->json(['error' => 'Miasto już jest w ulubionych'], 400);
        }
        
        $user->cities()->attach($city->id);
        
        return response()->json([
            'message' => 'Miasto dodane do ulubionych', 
            'city' => $city
        ]);
    }

public function removeCity(Request $request, $cityId)
{
    $user = $request->user();
    
    $city = City::where('openweather_id', $cityId)->first();
    
    if (!$city) {
        return response()->json(['error' => 'Nie znaleziono miasta'], 404);
    }
    
    // Odłącz miasto od użytkownika (usuń z ulubionych)
    $user->cities()->detach($city->id);
    
    // czy miasto jest domyślne - jeśli nie usuń je wraz z danymi historycznymi, tylko jeśli nie jest używane przez innych użytkowników
    $isDefaultCity = in_array($cityId, config('weather.default_cities'));
    
    if (!$isDefaultCity) {
        $isUsedByOthers = DB::table('user_cities')->where('city_id', $city->id)->exists();
        
        if (!$isUsedByOthers) {
            WeatherData::where('city_id', $city->id)->delete();
            
            // Usuń samo miasto
            $city->delete();
        }
    }
    
    return response()->json(['message' => 'Miasto usunięte z ulubionych']);
}

    public function searchCities(Request $request)
    {
        $query = $request->get('q');
        
        if (strlen($query) < 3) {
            return response()->json(['error' => 'Zapytanie musi zawierać co najmniej 3 znaki'], 400);
        }
        
        try {
            $response = Http::get('https://api.openweathermap.org/geo/1.0/direct', [
                'q' => $query,
                'limit' => 5,
                'appid' => config('weather.api_key')
            ]);
            
            if (!$response->successful()) {
                return response()->json(['error' => 'Nie udało się wyszukać miast'], 500);
            }
            
            $cities = $response->json();
            
            if (empty($cities)) {
                return response()->json([]);
            }
            
            $formattedCities = collect($cities)->map(function ($city) {
                return [
                    'id' => $this->getCityIdFromCoordinates($city['lat'], $city['lon']),
                    'name' => $city['name'],
                    'country' => $city['country'],
                    'state' => $city['state'] ?? null,
                    'lat' => $city['lat'],
                    'lon' => $city['lon']
                ];
            })->filter(function ($city) {
                return $city['id'] !== null;
            });
            
            return $formattedCities;
        } catch (\Exception $e) {
            Log::error('Błąd podczas wyszukiwania miast: ' . $e->getMessage());
            return response()->json(['error' => 'Wystąpił błąd podczas wyszukiwania miast'], 500);
        }
    }

    public function getHistoricalData(Request $request, $cityId)
    {
        try {
            $user = $request->user();
            $city = City::where('openweather_id', $cityId)->first();
            
            if (!$city) {
                return response()->json(['error' => 'Nie znaleziono miasta'], 404);
            }
            
            $isFavorite = $user->cities()->where('city_id', $city->id)->exists();
            
            if (!$isFavorite) {
                return response()->json(['error' => 'Brak dostępu do danych historycznych dla tego miasta'], 403);
            }
            
            $historicalData = $city->weatherData()
                ->orderBy('measured_at', 'asc')
                ->get()
                ->map(function ($data) {
                    return [
                        'timestamp' => $data->measured_at->timestamp * 1000, // Dla JavaScript Date
                        'date' => $data->measured_at->format('Y-m-d H:i:s'),
                        'temperature' => $data->temperature,
                        'humidity' => $data->humidity,
                        'pressure' => $data->pressure,
                        'wind_speed' => $data->wind_speed,
                        'weather_condition' => $data->weather_condition,
                        'weather_icon' => $data->weather_icon
                    ];
                });
            
            return response()->json([
                'city' => [
                    'id' => $city->id,
                    'name' => $city->name,
                    'country' => $city->country
                ],
                'data' => $historicalData
            ]);
        } catch (\Exception $e) {
            Log::error('Błąd podczas pobierania danych historycznych: ' . $e->getMessage());
            return response()->json(['error' => 'Wystąpił błąd podczas pobierania danych historycznych'], 500);
        }
    }

    private function getCityIdFromCoordinates($lat, $lon)
    {
        try {
            $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                'lat' => $lat,
                'lon' => $lon,
                'appid' => config('weather.api_key')
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['id'] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Błąd podczas pobierania ID miasta: ' . $e->getMessage());
        }
        
        return null;
    }

    private function loadDefaultCities()
    {
        $defaultCities = config('weather.default_cities');
        
        foreach ($defaultCities as $cityId) {
            if (City::where('openweather_id', $cityId)->exists()) {
                continue;
            }

            try {
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
                    
                    Log::info('Dodano domyślne miasto: ' . $data['name']);
                } else {
                    Log::error('Nie udało się dodać domyślnego miasta o ID: ' . $cityId);
                }
            } catch (\Exception $e) {
                Log::error('Błąd podczas dodawania domyślnego miasta: ' . $e->getMessage());
            }

            sleep(1);
        }
    }
}