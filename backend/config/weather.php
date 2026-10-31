<?php

return [
    'api_key' => env('OPENWEATHER_API_KEY', ''),
    // Adres bazowy API OpenWeatherMap (bez ścieżki - ścieżki podaje OpenWeatherClient)
    'api_url' => env('OPENWEATHER_API_URL', 'https://api.openweathermap.org'),
    // Pozostawione dla zgodności wstecznej
    'base_url' => 'https://api.openweathermap.org/data/2.5/',
    'update_interval' => env('WEATHER_UPDATE_INTERVAL', 30), // w minutach

    // Limit czasu zapytania HTTP (w sekundach) i ponowienia
    'timeout' => (int) env('WEATHER_TIMEOUT', 10),
    'retry_times' => (int) env('WEATHER_RETRY_TIMES', 2),
    'retry_sleep' => (int) env('WEATHER_RETRY_SLEEP', 200), // w milisekundach

    // Czas przechowywania w cache (w minutach)
    'cache' => [
        'current' => (int) env('WEATHER_CACHE_CURRENT', 10),
        'forecast' => (int) env('WEATHER_CACHE_FORECAST', 30),
        'search' => (int) env('WEATHER_CACHE_SEARCH', 60),
        'coords_lookup' => (int) env('WEATHER_CACHE_COORDS_LOOKUP', 60 * 24),
    ],

    // Obsługiwane języki opisów pogody
    'langs' => ['pl', 'en'],
    'default_lang' => 'pl',

    'favorites_limit' => (int) env('WEATHER_FAVORITES_LIMIT', 10),

    // Mapa pogodowa: proxy kafelków OpenWeather (klucz API nie trafia do przeglądarki)
    'map' => [
        'tiles_url' => env('WEATHER_TILES_URL', 'https://tile.openweathermap.org'),
        'layers' => ['temp_new', 'precipitation_new', 'clouds_new', 'wind_new', 'pressure_new'],
        'max_zoom' => 10,
        'tile_cache_minutes' => (int) env('WEATHER_TILE_CACHE_MINUTES', 60),
        // Magazyn cache dla kafelków (binarne PNG) - domyślnie pliki
        'tile_cache_store' => env('WEATHER_TILE_CACHE_STORE', 'file'),
        // Limit zapytań przeglądarki o kafelki na minutę (na adres IP; kafelki z cache też się liczą)
        'tile_rate_limit' => (int) env('WEATHER_TILE_RATE_LIMIT', 300),
    ],

    // One Call API 3.0 (wymaga osobnej subskrypcji) - domyślnie wyłączone
    'onecall_enabled' => (bool) env('WEATHER_ONECALL_ENABLED', false),
    'onecall_cache_minutes' => (int) env('WEATHER_ONECALL_CACHE', 30),

    // Dane historyczne: domyślny i maksymalny zakres (w dniach)
    'history_default_days' => 7,
    'history_max_days' => 366,

    // Przerwa między zapytaniami w weather:update i air:update (w milisekundach) - limit darmowego API
    // (1500 ms = 40 zapytań/min, poniżej weather.rate_limit_per_minute)
    'update_delay_ms' => (int) env('WEATHER_UPDATE_DELAY_MS', 1500),

    // Globalny limit zapytań wychodzących do API OpenWeatherMap na minutę (darmowy plan: 60/min).
    // Po wyczerpaniu zapytania nie są wysyłane - klient dostaje 503. 0 = bez limitu.
    // Suma rate_limit_per_minute i tile_rate_limit_per_minute nie powinna przekraczać 60.
    'rate_limit_per_minute' => (int) env('WEATHER_RATE_LIMIT_PER_MINUTE', 45),
    // Osobny, mniejszy limit dla kafelków mapy (mapa nie może wyczerpać limitu danych pogodowych)
    'tile_rate_limit_per_minute' => (int) env('WEATHER_TILE_RATE_LIMIT_PER_MINUTE', 15),

    'default_cities' => [
        756135,
        3094802,
        3081368,
        3088171,
        3099434,
        3093133,
        3096472,
        765876,
        3085128,
        759734,
    ],
];
