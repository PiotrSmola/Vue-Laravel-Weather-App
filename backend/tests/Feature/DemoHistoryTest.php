<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use App\Models\WeatherData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\FakesOpenWeather;
use Tests\TestCase;

class DemoHistoryTest extends TestCase
{
    use RefreshDatabase, FakesOpenWeather;

    public function test_command_generates_plausible_hourly_history(): void
    {
        $this->travelTo(Carbon::parse('2026-10-31 12:30:00', 'UTC'));
        $this->fakeOpenWeather();
        $user = User::factory()->create(['email' => 'demo@example.com']);

        $this->artisan('weather:demo-history', ['cityId' => 759734, '--days' => 10, '--user' => 'demo@example.com', '--seed' => 1])
            ->assertSuccessful();

        $city = City::where('openweather_id', 759734)->firstOrFail();
        $this->assertTrue($user->cities()->where('city_id', $city->id)->exists());

        // 10 dni co godzinę (+ pomiar z API zapisany przy tworzeniu miasta)
        $demo = WeatherData::where('city_id', $city->id)->where('measured_at', '>=', '2026-10-21 00:00:00');
        $this->assertSame(241, (clone $demo)->count());

        $this->assertGreaterThan(-25, (clone $demo)->min('temperature'));
        $this->assertLessThan(30, (clone $demo)->max('temperature'));
        $this->assertGreaterThanOrEqual(985, (clone $demo)->min('pressure'));
        $this->assertLessThanOrEqual(100, (clone $demo)->max('humidity'));

        // Ponowne uruchomienie nie tworzy duplikatów
        $this->artisan('weather:demo-history', ['cityId' => 759734, '--days' => 10])->assertSuccessful();
        $this->assertSame(241, (clone $demo)->count());
    }

    public function test_command_validates_options(): void
    {
        $this->fakeOpenWeather();

        $this->artisan('weather:demo-history', ['--days' => 0])->assertFailed();
        $this->artisan('weather:demo-history', ['--user' => 'brak@example.com'])->assertFailed();
    }
}
