<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use App\Models\WeatherData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HistoryExportTest extends TestCase
{
    use RefreshDatabase;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-31 12:00:00', 'UTC'));

        $this->city = City::factory()->create(['openweather_id' => 759734, 'name' => 'Rzeszów']);
        $user = User::factory()->create();
        $user->cities()->attach($this->city->id);
        Sanctum::actingAs($user);
    }

    public function test_csv_export_has_bom_headers_and_rows(): void
    {
        WeatherData::factory()->for($this->city)->at(Carbon::parse('2026-10-30 10:00:00', 'UTC'))->create([
            'temperature' => 12.3, 'feels_like' => 11.0, 'humidity' => 81, 'pressure' => 1009, 'wind_speed' => 4.5,
            'wind_direction' => 200, 'clouds' => 75, 'visibility' => 9000,
            'weather_condition' => 'Rain', 'weather_description' => 'słabe opady deszczu; mżawka', 'weather_icon' => '10d',
        ]);
        WeatherData::factory()->for($this->city)->at(Carbon::parse('2026-10-29 10:00:00', 'UTC'))->create(['temperature' => -1.5]);
        // Poza zakresem
        WeatherData::factory()->for($this->city)->at(Carbon::parse('2026-10-01 10:00:00', 'UTC'))->create();

        $response = $this->get('/api/cities/759734/history/export?from=2026-10-29&to=2026-10-30&format=csv', ['Accept' => 'text/csv']);

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('historia-rzeszow-2026-10-29-2026-10-30.csv', $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $lines = array_values(array_filter(explode("\n", substr($content, 3))));
        $this->assertCount(3, $lines);

        $header = str_getcsv($lines[0], ';');
        $this->assertSame('Data i godzina (UTC)', $header[0]);
        $this->assertContains('Temperatura (°C)', $header);
        $this->assertContains('Wilgotność (%)', $header);
        $this->assertCount(14, $header);

        // Rosnąco po czasie, liczby z przecinkiem dziesiętnym
        $first = str_getcsv($lines[1], ';');
        $this->assertSame('2026-10-29 10:00:00', $first[0]);
        $this->assertSame('-1,5', $first[1]);

        $second = str_getcsv($lines[2], ';');
        $this->assertSame(['2026-10-30 10:00:00', '12,3', '11,0', '81', '1009', '4,5', '200', '75', '9000', 'Rain', 'słabe opady deszczu; mżawka', '10d'],
            array_slice($second, 0, 12));
    }

    public function test_csv_uses_requested_timezone_and_default_format(): void
    {
        WeatherData::factory()->for($this->city)->at(Carbon::parse('2026-10-30 10:00:00', 'UTC'))->create();

        $content = $this->get('/api/cities/759734/history/export?tz=Europe/Warsaw')->assertOk()->streamedContent();

        $this->assertStringContainsString('Data i godzina (Europe/Warsaw)', $content);
        $this->assertStringContainsString('2026-10-30 11:00:00', $content);
    }

    public function test_empty_export_contains_only_header(): void
    {
        $content = $this->get('/api/cities/759734/history/export')->assertOk()->streamedContent();

        $this->assertCount(1, array_filter(explode("\n", $content)));
    }

    public function test_export_validation_and_access(): void
    {
        $this->getJson('/api/cities/759734/history/export?format=xlsx')->assertStatus(422)->assertJsonValidationErrors('format');
        $this->getJson('/api/cities/759734/history/export?from=2024-01-01&to=2026-10-31')->assertStatus(422);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/cities/759734/history/export')->assertForbidden();
        $this->getJson('/api/cities/1/history/export')->assertNotFound();
    }
}
