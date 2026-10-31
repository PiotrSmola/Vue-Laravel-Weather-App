<?php

// Jakość powietrza (OpenWeather Air Pollution API - dostępne w darmowym planie)
return [
    // Czas przechowywania w cache (w minutach)
    'cache' => [
        'current' => (int) env('AIR_CACHE_CURRENT', 15),
        'forecast' => (int) env('AIR_CACHE_FORECAST', 30),
    ],

    // Dane historyczne: domyślny i maksymalny zakres (w dniach)
    'history_default_days' => 7,
    'history_max_days' => 366,
];
