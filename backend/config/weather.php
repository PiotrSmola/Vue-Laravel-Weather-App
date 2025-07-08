<?php

return [
    'api_key' => env('OPENWEATHER_API_KEY', ''),
    'base_url' => 'https://api.openweathermap.org/data/2.5/',
    'update_interval' => env('WEATHER_UPDATE_INTERVAL', 30), // w minutach
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