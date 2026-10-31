<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use App\Models\WeatherData;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesOpenWeather;
use Tests\Fixtures\OpenWeatherFixtures;
use Tests\TestCase;

class UpdateWeatherCommandTest extends TestCase
{
    use RefreshDatabase, FakesOpenWeather;

    public function test_updates_default_cities_when_there_are_no_favorites(): void
    {
        $this->fakeOpenWeather();

        $this->artisan('weather:update')->assertSuccessful();

        $this->assertSame(10, City::count());
        $this->assertSame(10, WeatherData::count());
        Http::assertSentCount(10);
    }

    public function test_updates_union_of_favorites_and_defaults(): void
    {
        $this->fakeOpenWeather();
        $user = User::factory()->create();
        $paris = City::create(['openweather_id' => 2988507, 'name' => 'Paris', 'country' => 'FR', 'latitude' => 48.85, 'longitude' => 2.35]);
        $rzeszow = City::create(['openweather_id' => 759734, 'name' => 'Rzeszów', 'country' => 'PL', 'latitude' => 50.04, 'longitude' => 22.0]);
        $user->cities()->attach([$paris->id, $rzeszow->id]);

        $this->artisan('weather:update')->assertSuccessful();

        $this->assertSame(11, City::count());
        $this->assertSame(11, WeatherData::count());
        $this->assertSame(1, $paris->weatherData()->count());
        Http::assertSentCount(11);

        // Ponowne uruchomienie z tym samym czasem pomiaru nie tworzy duplikatów
        $this->artisan('weather:update')->assertSuccessful();
        $this->assertSame(11, WeatherData::count());
    }

    public function test_failures_are_counted_and_do_not_stop_the_command(): void
    {
        $this->fakeOpenWeather([
            '/data/2.5/weather' => fn ($request, $query) => (int) $query['id'] === 756135
                ? Http::response(OpenWeatherFixtures::error(), 404)
                : Http::response(OpenWeatherFixtures::current((int) $query['id'])),
        ]);

        $this->artisan('weather:update')
            ->expectsOutput('Sukces: 9 miast, Błędy: 1 miast')
            ->assertSuccessful();
    }

    public function test_command_is_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'weather:update'));

        $this->assertCount(1, $events);
        $this->assertSame('0,30 * * * *', $events->first()->expression);
    }
}
