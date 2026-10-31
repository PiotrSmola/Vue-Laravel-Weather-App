<?php

// Klucze VAPID dla powiadomień Web Push. Generowanie: php artisan webpush:vapid
return [

    'public_key' => env('VAPID_PUBLIC_KEY', ''),

    'private_key' => env('VAPID_PRIVATE_KEY', ''),

    // Kontakt dla usługi push (mailto: lub adres https)
    'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),

    // Czas przechowywania powiadomienia przez usługę push, gdy urządzenie jest offline (w sekundach)
    'ttl' => (int) env('WEBPUSH_TTL', 60 * 60 * 6),

    // Maksymalna liczba urządzeń (subskrypcji) na użytkownika
    'subscriptions_limit' => 10,

];
