<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesOpenWeather;
use Tests\TestCase;

class SearchCitiesTest extends TestCase
{
    use RefreshDatabase, FakesOpenWeather;

    public function test_search_returns_expected_shape(): void
    {
        $this->fakeOpenWeather();

        $this->getJson('/api/cities/search?q=Rzeszów')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonStructure([['id', 'name', 'country', 'state', 'lat', 'lon']])
            ->assertJsonPath('0.id', 759734)
            ->assertJsonPath('0.name', 'Rzeszów')
            ->assertJsonPath('0.state', 'Podkarpackie Voivodeship')
            ->assertJsonPath('1.state', null);
    }

    public function test_search_results_and_coordinate_lookups_are_cached(): void
    {
        $this->fakeOpenWeather();

        $this->getJson('/api/cities/search?q=Rzeszów')->assertOk();
        // 1 geocoding + 2 zapytania o ID (równolegle)
        Http::assertSentCount(3);

        // Inna wielkość liter / spacje - ten sam klucz cache
        $this->getJson('/api/cities/search?q=' . urlencode('  rzeszów '))->assertOk()->assertJsonCount(2);
        Http::assertSentCount(3);
    }

    public function test_coordinate_lookups_are_shared_between_queries(): void
    {
        $this->fakeOpenWeather();

        $this->getJson('/api/cities/search?q=Rzeszów')->assertOk();
        $this->getJson('/api/cities/search?q=Rzeszow')->assertOk();

        // Drugie wyszukiwanie: tylko geocoding, ID z cache współrzędnych
        Http::assertSentCount(4);
        $this->assertCount(2, Http::recorded(fn (Request $r) => str_contains($r->url(), '/geo/1.0/direct')));
    }

    public function test_short_query_is_rejected(): void
    {
        $this->fakeOpenWeather();

        $this->getJson('/api/cities/search?q=ab')
            ->assertStatus(400)
            ->assertJsonPath('error', 'Zapytanie musi zawierać co najmniej 3 znaki');

        Http::assertNothingSent();
    }

    public function test_search_api_failure_returns_generic_error(): void
    {
        $this->fakeOpenWeather([
            '/geo/*' => Http::response(['message' => 'internal secret'], 401),
        ]);

        $response = $this->getJson('/api/cities/search?q=Rzeszów')->assertStatus(502);
        $this->assertStringNotContainsString('secret', $response->getContent());
    }
}
