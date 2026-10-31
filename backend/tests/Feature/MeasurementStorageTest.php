<?php

namespace Tests\Feature;

use App\Events\WeatherUpdated;
use App\Models\WeatherData;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\OpenWeatherFixtures;
use Tests\TestCase;

class MeasurementStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_measurement_is_stored_once_and_event_fires_once(): void
    {
        Event::fake([WeatherUpdated::class]);
        $service = app(WeatherService::class);

        $city = $service->cityFromPayload(OpenWeatherFixtures::current(759734));
        $first = $service->storeMeasurement($city, OpenWeatherFixtures::current(759734));
        $second = $service->storeMeasurement($city, OpenWeatherFixtures::current(759734, ['main' => ['temp' => 19.0]]));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, WeatherData::count());
        $this->assertSame(19.0, WeatherData::first()->temperature);
        Event::assertDispatchedTimes(WeatherUpdated::class, 1);

        $service->storeMeasurement($city, OpenWeatherFixtures::current(759734, ['dt' => 1730386800]));

        $this->assertSame(2, WeatherData::count());
        Event::assertDispatchedTimes(WeatherUpdated::class, 2);
        Event::assertDispatched(WeatherUpdated::class, fn (WeatherUpdated $e) => $e->city->is($city)
            && $e->weatherData->measured_at->timestamp === 1730386800);
    }

    public function test_database_rejects_duplicate_measurement(): void
    {
        $service = app(WeatherService::class);
        $city = $service->cityFromPayload(OpenWeatherFixtures::current(759734));
        $row = $service->storeMeasurement($city, OpenWeatherFixtures::current(759734));

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        WeatherData::create(array_merge($row->getAttributes(), ['id' => null]));
    }

    public function test_city_is_not_duplicated(): void
    {
        $service = app(WeatherService::class);

        $a = $service->cityFromPayload(OpenWeatherFixtures::current(759734));
        $b = $service->cityFromPayload(OpenWeatherFixtures::current(759734, ['name' => 'Inna nazwa']));

        $this->assertTrue($a->is($b));
        $this->assertSame('Rzeszów', $b->name);
    }

    public function test_missing_optional_fields_do_not_break_storage(): void
    {
        $service = app(WeatherService::class);
        $payload = OpenWeatherFixtures::current(759734);
        unset($payload['wind']['deg'], $payload['visibility']);

        $city = $service->cityFromPayload($payload);
        $row = $service->storeMeasurement($city, $payload);

        $this->assertSame(0, $row->fresh()->wind_direction);
    }
}
