<?php

// Pierwszy adres z FRONTEND_URL (lista oddzielona przecinkami) - linki w mailach i powiadomieniach
$frontendUrls = array_values(array_filter(array_map('trim', explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173')))));

return [

    // Maksymalna liczba reguł alertów na użytkownika
    'rules_limit' => (int) env('ALERTS_RULES_LIMIT', 20),

    // Odstęp między kolejnymi powiadomieniami z tej samej reguły (w minutach)
    'default_cooldown' => (int) env('ALERTS_DEFAULT_COOLDOWN', 360),
    'min_cooldown' => 30,
    'max_cooldown' => 60 * 24 * 7,

    // Liczba zdarzeń na stronę w GET /api/alerts/events
    'events_per_page' => 15,

    // Okno prognozy dla alertu przymrozku (w godzinach)
    'frost_window_hours' => 24,

    // Domyślne progi dla alertów prognozy (gdy użytkownik nie poda własnego)
    'defaults' => [
        'rain_forecast_tomorrow' => 50, // prawdopodobieństwo opadów w %
        'frost_forecast' => 0,          // minimalna temperatura w °C
    ],

    // Adres frontendu (bez końcowego "/")
    'frontend_url' => rtrim($frontendUrls[0] ?? 'http://localhost:5173', '/'),

];
