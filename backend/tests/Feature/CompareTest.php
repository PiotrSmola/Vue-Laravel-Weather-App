<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\WeatherData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesOpenWeather;
use Tests\Fixtures\OpenWeatherFixtures;
use Tests\TestCase;

class CompareTest extends TestCase
{
    use RefreshDatabase, FakesOpenWeather;

    public function test_compare_returns_normalized_cities_in_requested_order(): void
    {
        $this->fakeOpenWeather();

        $response = $this->getJson('/api/compare?ids=3094802,756135&lang=pl')
            ->assertOk()
            ->assertJsonCount(2, 'cities')
            ->assertJsonPath('cities.0.id', 3094802)
            ->assertJsonPath('cities.0.name', 'Kraków')
            ->assertJsonPath('cities.0.country', 'PL')
            ->assertJsonPath('cities.1.id', 756135)
            ->assertJsonPath('cities.1.name', 'Warszawa')
            ->assertJsonPath('cities.0.current.temp', 18.5)
            ->assertJsonPath('cities.0.current.wind_deg', 240)
            ->assertJsonPath('cities.0.current.condition', 'Clear')
            ->assertJsonPath('cities.0.current.dt', 1730383200)
            ->assertJsonCount(8, 'cities.0.forecast')
            ->assertJsonPath('cities.0.forecast.0', [
                'dt' => 1730386800, 'temp' => 15, 'pop' => 0.1, 'wind_speed' => 4.1, 'icon' => '04d',
            ])
            ->assertJsonStructure(['cities' => [[
                'id', 'name', 'country',
                'current' => ['temp', 'feels_like', 'humidity', 'pressure', 'wind_speed', 'wind_deg', 'condition', 'description', 'icon', 'dt'],
                'forecast' => [['dt', 'temp', 'pop', 'wind_speed', 'icon']],
            ]]]);

        // 2 miasta x (pogoda + prognoza)
        Http::assertSentCount(4);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'lang=pl') && str_contains($request->url(), 'units=metric'));

        // Aktualna pogoda zapisuje miasta i pomiary (jak /weather/{id})
        $this->assertSame(2, City::count());
        $this->assertSame(2, WeatherData::count());

        $this->assertNotNull($response->json('cities.1.forecast.7.temp'));
    }

    public function test_compare_uses_shared_cache(): void
    {
        $this->fakeOpenWeather();

        // Pogoda dla Krakowa jest już w cache (np. ze strony szczegółów)
        $this->getJson('/api/weather/3094802')->assertOk();
        $this->getJson('/api/weather/forecast/3094802')->assertOk();
        Http::assertSentCount(2);

        $this->getJson('/api/compare?ids=3094802,756135')->assertOk();
        Http::assertSentCount(4);

        // Drugie porównanie - wszystko z cache
        $this->getJson('/api/compare?ids=756135,3094802')->assertOk();
        Http::assertSentCount(4);
    }

    public function test_compare_validation(): void
    {
        $this->fakeOpenWeather();

        foreach (['', 'abc', '1', '1,abc', '1,,2', '-1,2', '1,2,3,4,5', '5,5', '0,1', '1.5,2'] as $ids) {
            $this->getJson('/api/compare?ids=' . urlencode($ids))
                ->assertStatus(422)
                ->assertJsonStructure(['error']);
        }

        $this->getJson('/api/compare')->assertStatus(422);
        $this->getJson('/api/compare?ids=1,2&lang=de')->assertStatus(422);

        Http::assertNothingSent();

        $this->getJson('/api/compare?ids=759734,756135,3094802,3081368')->assertOk()->assertJsonCount(4, 'cities');
    }

    public function test_unknown_city_returns_404_without_leaking_details(): void
    {
        $this->fakeOpenWeather([
            '/data/2.5/*' => function (Request $request, array $query) {
                if ((int) ($query['id'] ?? 0) === 999) {
                    return Http::response(OpenWeatherFixtures::error(404), 404);
                }

                return str_contains($request->url(), 'forecast')
                    ? Http::response(OpenWeatherFixtures::forecast((int) $query['id']))
                    : Http::response(OpenWeatherFixtures::current((int) $query['id']));
            },
        ]);

        $this->getJson('/api/compare?ids=759734,999')
            ->assertNotFound()
            ->assertExactJson(['error' => 'Nie znaleziono miasta']);
    }

    public function test_api_failure_returns_502(): void
    {
        $this->fakeOpenWeather([
            '/data/2.5/*' => Http::response(['cod' => 500, 'message' => 'secret internal error'], 500),
        ]);

        $response = $this->getJson('/api/compare?ids=759734,756135')->assertStatus(502);
        $this->assertStringNotContainsString('secret', $response->getContent());
    }
}
