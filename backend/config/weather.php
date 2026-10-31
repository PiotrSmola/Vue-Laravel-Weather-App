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

    // Dane historyczne: domyślny i maksymalny zakres (w dniach)
    'history_default_days' => 7,
    'history_max_days' => 366,

    // Przerwa między zapytaniami w weather:update (w milisekundach) - limit darmowego API
    'update_delay_ms' => (int) env('WEATHER_UPDATE_DELAY_MS', 1000),

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
