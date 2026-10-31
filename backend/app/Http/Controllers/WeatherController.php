<?php

namespace App\Http\Controllers;

use App\Services\WeatherService;
use Illuminate\Http\Request;

/**
 * Aktualna pogoda i prognoza. Błędy API (WeatherApiException) obsługuje bootstrap/app.php.
 * Wszystkie endpointy przyjmują opcjonalny ?lang=pl|en. Jednostki zawsze metryczne.
 */
class WeatherController extends Controller
{
    public function __construct(protected WeatherService $weather)
    {
    }

    public function getCurrentWeather(Request $request, int $cityId)
    {
        return $this->weather->currentForOpenWeatherId($cityId, $this->lang($request));
    }

    public function getWeatherByCoordinates(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
        ]);

        return $this->weather->currentByQuery(
            ['lat' => (float) $request->lat, 'lon' => (float) $request->lon],
            $this->lang($request)
        );
    }

    public function getWeatherByCity(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        return $this->weather->currentByQuery(['q' => trim($request->q)], $this->lang($request));
    }

    public function getForecast(Request $request, int $cityId)
    {
        return $this->weather->forecastForOpenWeatherId($cityId, $this->lang($request));
    }

    public function getForecastByCoordinates(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
        ]);

        return $this->weather->forecastByQuery(
            ['lat' => (float) $request->lat, 'lon' => (float) $request->lon],
            $this->lang($request)
        );
    }

    public function getForecastByCity(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        return $this->weather->forecastByQuery(['q' => trim($request->q)], $this->lang($request));
    }
}
