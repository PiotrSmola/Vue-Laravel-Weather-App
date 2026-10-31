<?php

namespace Tests\Concerns;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Fixtures\OpenWeatherFixtures;

/**
 * Podmienia API OpenWeatherMap na odpowiedzi z OpenWeatherFixtures.
 *
 * Użycie:
 *   $this->fakeOpenWeather();                                     // domyślne odpowiedzi
 *   $this->fakeOpenWeather([
 *       '/data/2.5/air_pollution' => Http::response([...]),        // własna odpowiedź dla ścieżki
 *       '/data/3.0/*' => fn (Request $r, array $query) => Http::response([...]),
 *   ]);
 *
 * Klucze nadpisań to wzorce ścieżek (Str::is). Nieznane ścieżki zwracają 404.
 */
trait FakesOpenWeather
{
    protected function fakeOpenWeather(array $overrides = []): void
    {
        Http::fake(function (Request $request) use ($overrides) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '/';
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            foreach ($overrides as $pattern => $response) {
                if (Str::is($pattern, $path)) {
                    return is_callable($response) ? $response($request, $query) : $response;
                }
            }

            return match ($path) {
                '/data/2.5/weather' => Http::response(OpenWeatherFixtures::current($this->fakeCityId($query))),
                '/data/2.5/forecast' => Http::response(OpenWeatherFixtures::forecast($this->fakeCityId($query))),
                '/geo/1.0/direct' => Http::response(OpenWeatherFixtures::geocode()),
                '/geo/1.0/reverse' => Http::response(array_slice(OpenWeatherFixtures::geocode(), 0, 1)),
                default => Http::response(OpenWeatherFixtures::error(404, 'not found'), 404),
            };
        });
    }

    // ID miasta dla zapytania: ?id=, ?q= (nazwa z listy) lub ?lat=&lon= (najbliższe z listy)
    protected function fakeCityId(array $query): int
    {
        if (isset($query['id'])) {
            return (int) $query['id'];
        }

        if (isset($query['q'])) {
            foreach (OpenWeatherFixtures::CITIES as $id => [$name]) {
                if (mb_strtolower($name) === mb_strtolower(trim($query['q']))) {
                    return $id;
                }
            }

            return 759734;
        }

        if (isset($query['lat'], $query['lon'])) {
            $best = 759734;
            $bestDistance = INF;

            foreach (OpenWeatherFixtures::CITIES as $id => [, $lat, $lon]) {
                $distance = ($lat - (float) $query['lat']) ** 2 + ($lon - (float) $query['lon']) ** 2;
                if ($distance < $bestDistance) {
                    [$best, $bestDistance] = [$id, $distance];
                }
            }

            return $best;
        }

        return 759734;
    }
}
