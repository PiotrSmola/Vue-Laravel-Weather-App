<?php

namespace App\Support;

// Linki do frontendu (np. w mailach). FRONTEND_URL może zawierać kilka adresów
// oddzielonych przecinkami (CORS) - do linków używany jest pierwszy z nich.
class FrontendUrl
{
    public static function base(): string
    {
        $first = collect(explode(',', (string) config('app.frontend_url')))
            ->map(fn ($url) => trim($url))
            ->first(fn ($url) => $url !== '');

        return rtrim($first ?: 'http://localhost:5173', '/');
    }

    public static function to(string $path, array $query = []): string
    {
        $url = self::base() . '/' . ltrim($path, '/');

        return $query ? $url . '?' . http_build_query($query) : $url;
    }
}
