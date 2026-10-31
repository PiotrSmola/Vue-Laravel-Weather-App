<?php

namespace App\Services\Alerts;

/**
 * Wynik spełnionej reguły: wartość (metryczna), szczegóły i opcjonalny klucz deduplikacji
 * (np. data prognozy - ta sama prognoza nie jest zgłaszana ponownie).
 */
class AlertCheckResult
{
    public function __construct(
        public ?float $value,
        public array $details = [],
        public ?string $dedupeKey = null,
    ) {
    }
}
