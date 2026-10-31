<?php

namespace App\Support;

/**
 * Indeksy jakości powietrza liczone ze stężeń składników (µg/m³).
 *
 * - OpenWeather (skala 1-5) - indeks zwracany przez API; tu służy do opisu poziomu
 *   i wyznaczenia zanieczyszczenia dominującego.
 * - Europejski indeks jakości powietrza (EEA, skala 1-6).
 * - Polski indeks jakości powietrza (GIOŚ, skala 1-6).
 *
 * Progi to GÓRNE granice kolejnych poziomów (ostatni poziom: powyżej ostatniego progu).
 * Uwaga: EEA i GIOŚ liczą PM2.5/PM10 ze średnich 24-godzinnych (EEA) lub 1-godzinnych (GIOŚ)
 * ze stacji pomiarowych - my używamy wartości godzinowych z modelu OpenWeather,
 * więc wynik jest szacunkiem, a nie oficjalnym indeksem.
 */
class AirQualityIndex
{
    // Nazwy składników do wyświetlenia
    public const NAMES = [
        'co' => 'CO',
        'no' => 'NO',
        'no2' => 'NO₂',
        'o3' => 'O₃',
        'so2' => 'SO₂',
        'pm2_5' => 'PM2.5',
        'pm10' => 'PM10',
        'nh3' => 'NH₃',
    ];

    // Polskie etykiety poziomów indeksu OpenWeather 1-5
    public const OWM_LABELS = [
        1 => 'Dobra',
        2 => 'Dostateczna',
        3 => 'Średnia',
        4 => 'Zła',
        5 => 'Bardzo zła',
    ];

    /**
     * Progi indeksu OpenWeather (https://openweathermap.org/air-pollution-index-levels):
     * Good / Fair / Moderate / Poor / Very Poor.
     */
    public const OWM_THRESHOLDS = [
        'so2' => [20, 80, 250, 350],
        'no2' => [40, 70, 150, 200],
        'pm10' => [20, 50, 100, 200],
        'pm2_5' => [10, 25, 50, 75],
        'o3' => [60, 100, 140, 180],
        'co' => [4400, 9400, 12400, 15400],
    ];

    // Europejski indeks (EEA): Dobra / Dostateczna / Umiarkowana / Zła / Bardzo zła / Skrajnie zła
    public const EUROPEAN_LABELS = [
        1 => 'Dobra',
        2 => 'Dostateczna',
        3 => 'Umiarkowana',
        4 => 'Zła',
        5 => 'Bardzo zła',
        6 => 'Skrajnie zła',
    ];

    /**
     * Progi europejskiego indeksu jakości powietrza (European Environment Agency,
     * https://airindex.eea.europa.eu - wersja obowiązująca od 2021 r.).
     */
    public const EUROPEAN_THRESHOLDS = [
        'pm2_5' => [10, 20, 25, 50, 75],
        'pm10' => [20, 40, 50, 100, 150],
        'no2' => [40, 90, 120, 230, 340],
        'o3' => [50, 100, 130, 240, 380],
        'so2' => [100, 200, 350, 500, 750],
    ];

    // Polski indeks (GIOŚ): Bardzo dobry / Dobry / Umiarkowany / Dostateczny / Zły / Bardzo zły
    public const POLISH_LABELS = [
        1 => 'Bardzo dobry',
        2 => 'Dobry',
        3 => 'Umiarkowany',
        4 => 'Dostateczny',
        5 => 'Zły',
        6 => 'Bardzo zły',
    ];

    /**
     * Progi Polskiego Indeksu Jakości Powietrza (GIOŚ, stężenia 1-godzinne,
     * https://powietrze.gios.gov.pl - wersja z 2020 r.).
     */
    public const POLISH_THRESHOLDS = [
        'pm2_5' => [13, 35, 55, 75, 110],
        'pm10' => [20, 50, 80, 110, 150],
        'no2' => [40, 100, 150, 230, 400],
        'o3' => [70, 120, 150, 180, 240],
        'so2' => [50, 100, 200, 350, 500],
    ];

    // Poziom (1..count(progi)+1) dla stężenia wg podanych progów
    public static function level(float $value, array $thresholds): int
    {
        foreach ($thresholds as $index => $upper) {
            if ($value < $upper) {
                return $index + 1;
            }
        }

        return count($thresholds) + 1;
    }

    /**
     * Indeks wg tabeli progów: poziom = najgorszy poziom spośród składników.
     * Zwraca ['level' => int, 'label' => string, 'pollutant' => klucz składnika] lub null, gdy brak danych.
     */
    public static function compute(array $components, array $thresholdTable, array $labels): ?array
    {
        $worst = null;

        foreach ($thresholdTable as $key => $thresholds) {
            if (!isset($components[$key]) || !is_numeric($components[$key])) {
                continue;
            }

            $level = self::level((float) $components[$key], $thresholds);

            if ($worst === null || $level > $worst['level']) {
                $worst = ['level' => $level, 'pollutant' => $key];
            }
        }

        if ($worst === null) {
            return null;
        }

        return [
            'level' => $worst['level'],
            'label' => $labels[$worst['level']],
            'pollutant' => $worst['pollutant'],
        ];
    }

    public static function european(array $components): ?array
    {
        return self::compute($components, self::EUROPEAN_THRESHOLDS, self::EUROPEAN_LABELS);
    }

    public static function polish(array $components): ?array
    {
        return self::compute($components, self::POLISH_THRESHOLDS, self::POLISH_LABELS);
    }

    public static function owmLabel(int $aqi): string
    {
        return self::OWM_LABELS[$aqi] ?? 'Brak danych';
    }

    /**
     * Zanieczyszczenie dominujące: składnik o najwyższym wyniku w skali OpenWeather.
     * Wynik = (poziom - 1) + pozycja wewnątrz przedziału poziomu (0..1), dzięki czemu
     * przy równym poziomie wygrywa składnik bliższy przekroczenia kolejnego progu.
     * Zwraca ['key', 'name', 'value', 'unit', 'level'] lub null.
     */
    public static function dominant(array $components): ?array
    {
        $best = null;

        foreach (self::OWM_THRESHOLDS as $key => $thresholds) {
            if (!isset($components[$key]) || !is_numeric($components[$key])) {
                continue;
            }

            $value = (float) $components[$key];
            $level = self::level($value, $thresholds);
            $lower = $level === 1 ? 0 : $thresholds[$level - 2];
            $upper = $thresholds[$level - 1] ?? null;

            // Najwyższy poziom nie ma górnej granicy - liczymy względem dwukrotności dolnego progu
            $upper ??= $lower * 2;
            $position = $upper > $lower ? min(1, max(0, ($value - $lower) / ($upper - $lower))) : 1;
            $score = ($level - 1) + $position;

            if ($best === null || $score > $best['score']) {
                $best = ['key' => $key, 'value' => $value, 'level' => $level, 'score' => $score];
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'key' => $best['key'],
            'name' => self::NAMES[$best['key']],
            'value' => round($best['value'], 2),
            'unit' => 'µg/m³',
            'level' => $best['level'],
        ];
    }
}
