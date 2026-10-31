<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesOpenWeather;
use Tests\TestCase;

class CitiesIndexTest extends TestCase
{
    use RefreshDatabase, FakesOpenWeather;

    public function test_guest_gets_all_default_cities(): void
    {
        $this->fakeOpenWeather();

        $response = $this->getJson('/api/cities')->assertOk()->assertJsonCount(10);

        $this->assertSame(
            array_map('intval', config('weather.default_cities')),
            collect($response->json())->pluck('openweather_id')->all()
        );
        $this->assertSame(10, City::count());
    }

    public function test_non_default_cities_are_not_listed(): void
    {
        $this->fakeOpenWeather();
        City::create(['openweather_id' => 1, 'name' => 'Inne', 'country' => 'DE', 'latitude' => 1, 'longitude' => 1]);

        $this->getJson('/api/cities')->assertOk()->assertJsonCount(10)->assertJsonMissing(['name' => 'Inne']);
    }

    public function test_authenticated_user_does_not_see_own_favorites_only(): void
    {
        $this->fakeOpenWeather();
        $this->getJson('/api/cities')->assertOk();

        $me = User::factory()->create();
        $other = User::factory()->create();
        $me->cities()->attach(City::where('openweather_id', 759734)->value('id'));
        $other->cities()->attach(City::where('openweather_id', 756135)->value('id'));

        $token = $me->createToken('t')->plainTextToken;

        $ids = collect($this->withToken($token)->getJson('/api/cities')->assertOk()->json())->pluck('openweather_id');

        $this->assertCount(9, $ids);
        $this->assertNotContains(759734, $ids);
        $this->assertContains(756135, $ids); // ulubione innego użytkownika nadal widoczne

        // Gość widzi wszystkie
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/cities')->assertJsonCount(10);
    }
}
