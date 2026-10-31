<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Błąd komunikacji z API OpenWeatherMap.
 * Treść odpowiedzi API trafia wyłącznie do logów, nigdy do odpowiedzi JSON.
 */
class WeatherApiException extends RuntimeException
{
    public function __construct(
        string $message = 'Błąd API OpenWeatherMap',
        public readonly ?int $status = null,
        public readonly ?string $upstreamBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    // Czy API zwróciło 404 (np. nieistniejące miasto)
    public function isNotFound(): bool
    {
        return $this->status === 404;
    }
}
