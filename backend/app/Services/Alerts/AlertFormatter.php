<?php

namespace App\Services\Alerts;

use App\Models\AlertRule;
use App\Models\City;
use App\Models\User;

/**
 * Teksty alertów (po polsku) z wartościami w jednostkach wybranych przez użytkownika.
 * Wejście zawsze metryczne: °C, m/s, %, hPa.
 */
class AlertFormatter
{
    public const TYPE_LABELS = [
        'temp_below' => 'Temperatura poniżej progu',
        'temp_above' => 'Temperatura powyżej progu',
        'wind_above' => 'Silny wiatr',
        'humidity_above' => 'Wysoka wilgotność',
        'pressure_below' => 'Niskie ciśnienie',
        'condition' => 'Zjawisko pogodowe',
        'rain_forecast_tomorrow' => 'Deszcz jutro',
        'frost_forecast' => 'Przymrozek w ciągu 24 h',
    ];

    public const CONDITION_LABELS = [
        'rain' => 'deszcz',
        'snow' => 'śnieg',
        'thunderstorm' => 'burza',
        'fog' => 'mgła',
    ];

    public function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? $type;
    }

    public function conditionLabel(?string $condition): string
    {
        return self::CONDITION_LABELS[$condition] ?? (string) $condition;
    }

    // Wartość z jednostką, np. "-3.5°C", "45 km/h", "1002 hPa", "80%"
    public function formatValue(string $type, ?float $value, ?User $user = null): string
    {
        if ($value === null) {
            return '—';
        }

        $units = $user?->units ?? 'metric';
        $windUnit = $user?->wind_unit ?? 'kmh';

        return match ($type) {
            'temp_below', 'temp_above', 'frost_forecast' => $units === 'imperial'
                ? $this->number($value * 9 / 5 + 32, 1) . '°F'
                : $this->number($value, 1) . '°C',
            'wind_above' => match ($windUnit) {
                'ms' => $this->number($value, 1) . ' m/s',
                'mph' => $this->number($value * 2.236936, 0) . ' mph',
                default => $this->number($value * 3.6, 0) . ' km/h',
            },
            'pressure_below' => $units === 'imperial'
                ? $this->number($value * 0.0295299830714, 2) . ' inHg'
                : $this->number($value, 0) . ' hPa',
            'humidity_above', 'rain_forecast_tomorrow' => $this->number($value, 0) . '%',
            default => $this->number($value, 1),
        };
    }

    // Krótki opis warunku reguły, np. "temperatura < -5°C", "zjawisko: burza"
    public function ruleSummary(AlertRule $rule, ?User $user = null): string
    {
        $threshold = $this->formatValue($rule->type, $rule->effectiveThreshold(), $user);

        return match ($rule->type) {
            'temp_below' => 'temperatura < ' . $threshold,
            'temp_above' => 'temperatura > ' . $threshold,
            'wind_above' => 'wiatr > ' . $threshold,
            'humidity_above' => 'wilgotność > ' . $threshold,
            'pressure_below' => 'ciśnienie < ' . $threshold,
            'condition' => 'zjawisko: ' . $this->conditionLabel($rule->condition),
            'rain_forecast_tomorrow' => 'szansa deszczu jutro ≥ ' . $threshold,
            'frost_forecast' => 'temperatura minimalna < ' . $threshold . ' w ciągu 24 h',
            default => $rule->type,
        };
    }

    public function title(AlertRule $rule, City $city): string
    {
        $label = trim((string) $rule->label);

        return $city->name . ': ' . ($label !== '' ? $label : $this->typeLabel($rule->type));
    }

    // Treść powiadomienia dla wyniku oceny reguły
    public function message(AlertRule $rule, City $city, AlertCheckResult $result, ?User $user = null): string
    {
        $value = $this->formatValue($rule->type, $result->value, $user);
        $threshold = $this->formatValue($rule->type, $rule->effectiveThreshold(), $user);

        return match ($rule->type) {
            'temp_below' => "Temperatura w mieście {$city->name} spadła do {$value} (próg: {$threshold}).",
            'temp_above' => "Temperatura w mieście {$city->name} wzrosła do {$value} (próg: {$threshold}).",
            'wind_above' => "Wiatr w mieście {$city->name} osiąga {$value} (próg: {$threshold}).",
            'humidity_above' => "Wilgotność w mieście {$city->name} wynosi {$value} (próg: {$threshold}).",
            'pressure_below' => "Ciśnienie w mieście {$city->name} spadło do {$value} (próg: {$threshold}).",
            'condition' => 'W mieście ' . $city->name . ' występuje: ' . ($result->details['description'] ?? $this->conditionLabel($rule->condition)) . '.',
            'rain_forecast_tomorrow' => "Jutro ({$result->details['date']}) w mieście {$city->name} prognozowany jest deszcz - prawdopodobieństwo do {$value} (próg: {$threshold})"
                . (!empty($result->details['rain_mm']) ? ', suma opadów ok. ' . $this->number($result->details['rain_mm'], 1) . ' mm.' : '.'),
            'frost_forecast' => "W ciągu 24 h w mieście {$city->name} prognozowany jest spadek temperatury do {$value} (próg: {$threshold})"
                . (!empty($result->details['at']) ? ' - ' . $result->details['at'] . '.' : '.'),
            default => $this->typeLabel($rule->type) . ': ' . $value,
        };
    }

    protected function number(float $value, int $decimals): string
    {
        $rounded = round($value, $decimals);

        if ($rounded == 0) {
            $rounded = 0.0; // bez "-0"
        }

        return rtrim(rtrim(number_format($rounded, $decimals, '.', ''), '0'), '.') ?: '0';
    }
}
