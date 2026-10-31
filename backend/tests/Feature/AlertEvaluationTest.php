<?php

namespace Tests\Feature;

use App\Models\AlertEvent;
use App\Notifications\AlertTriggered;
use App\Notifications\Channels\WebPushChannel;
use App\Services\Alerts\AlertEvaluator;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesAlerts;
use Tests\Fixtures\OpenWeatherFixtures;
use Tests\TestCase;

class AlertEvaluationTest extends TestCase
{
    use RefreshDatabase, CreatesAlerts;

    protected function evaluator(): AlertEvaluator
    {
        return app(AlertEvaluator::class);
    }

    public static function currentRuleCases(): array
    {
        // [atrybuty reguły, pomiar spełniający, pomiar niespełniający]
        return [
            'temp_below' => [['type' => 'temp_below', 'threshold' => 0], ['temperature' => -2.5], ['temperature' => 0]],
            'temp_above' => [['type' => 'temp_above', 'threshold' => 30], ['temperature' => 31], ['temperature' => 30]],
            'wind_above' => [['type' => 'wind_above', 'threshold' => 10], ['wind_speed' => 12.5], ['wind_speed' => 9.9]],
            'humidity_above' => [['type' => 'humidity_above', 'threshold' => 90], ['humidity' => 95], ['humidity' => 90]],
            'pressure_below' => [['type' => 'pressure_below', 'threshold' => 1000], ['pressure' => 990], ['pressure' => 1000]],
            'condition rain' => [['type' => 'condition', 'threshold' => null, 'condition' => 'rain'], ['weather_condition' => 'Drizzle', 'weather_description' => 'mżawka'], ['weather_condition' => 'Snow']],
            'condition snow' => [['type' => 'condition', 'threshold' => null, 'condition' => 'snow'], ['weather_condition' => 'Snow'], ['weather_condition' => 'Rain']],
            'condition thunderstorm' => [['type' => 'condition', 'threshold' => null, 'condition' => 'thunderstorm'], ['weather_condition' => 'Thunderstorm'], ['weather_condition' => 'Clouds']],
            'condition fog' => [['type' => 'condition', 'threshold' => null, 'condition' => 'fog'], ['weather_condition' => 'Mist'], ['weather_condition' => 'Clear']],
        ];
    }

    /**
     * @dataProvider currentRuleCases
     */
    public function test_current_rule_types(array $ruleAttributes, array $matching, array $notMatching): void
    {
        Notification::fake();
        [$user, $city] = $this->userWithFavorite();
        $rule = $this->makeRule($user, $city, $ruleAttributes);

        $this->assertSame(0, $this->evaluator()->evaluateCurrent($city, $this->makeMeasurement($city, $notMatching)));
        Notification::assertNothingSent();

        $this->assertSame(1, $this->evaluator()->evaluateCurrent($city, $this->makeMeasurement($city, $matching)));
        Notification::assertSentTo($user, AlertTriggered::class);

        $event = AlertEvent::sole();
        $this->assertSame($rule->id, $event->alert_rule_id);
        $this->assertSame($user->id, $event->user_id);
        $this->assertNull($event->read_at);
        $this->assertStringStartsWith('Rzeszów: ', $event->title);
        $this->assertNotNull($rule->fresh()->last_triggered_at);
    }

    public function test_cooldown_prevents_duplicate_notifications(): void
    {
        Notification::fake();
        [$user, $city] = $this->userWithFavorite();
        $rule = $this->makeRule($user, $city, ['cooldown_minutes' => 60]);

        $this->assertSame(1, $this->evaluator()->evaluateCurrent($city, $this->makeMeasurement($city, ['temperature' => -1])));
        $this->assertSame(0, $this->evaluator()->evaluateCurrent($city, $this->makeMeasurement($city, ['temperature' => -3])));

        $this->travel(59)->minutes();
        $this->assertSame(0, $this->evaluator()->evaluateCurrent($city, $this->makeMeasurement($city, ['temperature' => -3])));

        $this->travel(2)->minutes();
        $this->assertSame(1, $this->evaluator()->evaluateCurrent($city, $this->makeMeasurement($city, ['temperature' => -3])));

        Notification::assertSentToTimes($user, AlertTriggered::class, 2);
        $this->assertSame(2, AlertEvent::count());

        // Atomowa rezerwacja: druga próba w tym samym momencie się nie powiedzie
        $this->assertFalse($rule->fresh()->claimTrigger());
    }

    public function test_inactive_rules_other_cities_and_removed_favorites_are_ignored(): void
    {
        Notification::fake();
        [$user, $city] = $this->userWithFavorite();
        $warsaw = $this->makeCity(756135);
        $user->cities()->attach($warsaw->id);

        $this->makeRule($user, $city, ['active' => false]);
        $this->makeRule($user, $warsaw);

        $this->assertSame(0, $this->evaluator()->evaluateCurrent($city, $this->makeMeasurement($city, ['temperature' => -10])));

        // Miasto usunięte z ulubionych - reguła nie działa
        $user->cities()->detach($warsaw->id);
        $this->assertSame(0, $this->evaluator()->evaluateCurrent($warsaw, $this->makeMeasurement($warsaw, ['temperature' => -10])));

        Notification::assertNothingSent();
        $this->assertSame(0, AlertEvent::count());
    }

    public function test_rules_of_multiple_users_are_evaluated_independently(): void
    {
        Notification::fake();
        [$first, $city] = $this->userWithFavorite();
        [$second] = $this->userWithFavorite();
        $this->makeRule($first, $city, ['type' => 'temp_above', 'threshold' => 20]);
        $this->makeRule($second, $city, ['type' => 'temp_above', 'threshold' => 25]);

        $this->assertSame(1, $this->evaluator()->evaluateCurrent($city, $this->makeMeasurement($city, ['temperature' => 22])));

        Notification::assertSentTo($first, AlertTriggered::class);
        Notification::assertNotSentTo($second, AlertTriggered::class);
    }

    public function test_new_measurement_triggers_listener(): void
    {
        Notification::fake();
        [$user, $city] = $this->userWithFavorite();
        $this->makeRule($user, $city, ['type' => 'temp_above', 'threshold' => 15, 'channels' => ['mail', 'push']]);

        // storeMeasurement wysyła WeatherUpdated -> EvaluateCurrentAlerts (kolejka sync w testach)
        app(WeatherService::class)->storeMeasurement($city, OpenWeatherFixtures::current(759734));

        Notification::assertSentTo($user, AlertTriggered::class, function (AlertTriggered $notification, array $channels) {
            return $channels === ['mail', WebPushChannel::class]
                && $notification->event->value === 18.5;
        });

        // Ten sam pomiar zapisany ponownie nie wysyła zdarzenia
        app(WeatherService::class)->storeMeasurement($city, OpenWeatherFixtures::current(759734));
        Notification::assertSentToTimes($user, AlertTriggered::class, 1);
    }

    public function test_notification_respects_channels_and_is_queued(): void
    {
        Notification::fake();
        [$user, $city] = $this->userWithFavorite();
        $this->makeRule($user, $city, ['channels' => ['push']]);

        $this->evaluator()->evaluateCurrent($city, $this->makeMeasurement($city, ['temperature' => -4]));

        Notification::assertSentTo($user, AlertTriggered::class, fn ($n, array $channels) => $channels === [WebPushChannel::class]);
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, new AlertTriggered(AlertEvent::sole()));
    }

    public function test_mail_content_uses_user_units_and_links_to_city(): void
    {
        Notification::fake();
        config(['alerts.frontend_url' => 'https://pogoda.example']);
        [$user, $city] = $this->userWithFavorite(759734, ['units' => 'imperial', 'wind_unit' => 'mph', 'name' => 'Jan']);
        $this->makeRule($user, $city, ['type' => 'temp_below', 'threshold' => 0, 'label' => 'Mróz w Rzeszowie']);

        $this->evaluator()->evaluateCurrent($city, $this->makeMeasurement($city, ['temperature' => -5]));

        $event = AlertEvent::sole();
        $this->assertSame('Rzeszów: Mróz w Rzeszowie', $event->title);
        $this->assertSame('Temperatura w mieście Rzeszów spadła do 23°F (próg: 32°F).', $event->message);

        $mail = (new AlertTriggered($event))->toMail($user);
        $this->assertSame('Alert pogodowy: Rzeszów: Mróz w Rzeszowie', $mail->subject);

        $html = (string) $mail->render();
        $this->assertStringContainsString('Cześć Jan', $html);
        $this->assertStringContainsString('https://pogoda.example/weather/759734', $html);
        $this->assertStringContainsString('23°F', $html);
        $this->assertStringContainsString('32°F', $html);

        $push = (new AlertTriggered($event))->toWebPush($user);
        $this->assertSame('/weather/759734', $push['url']);
        $this->assertSame($event->title, $push['title']);
    }

    public function test_wind_message_in_user_wind_unit(): void
    {
        Notification::fake();
        [$user, $city] = $this->userWithFavorite(759734, ['wind_unit' => 'kmh']);
        $this->makeRule($user, $city, ['type' => 'wind_above', 'threshold' => 10]);

        $this->evaluator()->evaluateCurrent($city, $this->makeMeasurement($city, ['wind_speed' => 15]));

        $this->assertSame('Wiatr w mieście Rzeszów osiąga 54 km/h (próg: 36 km/h).', AlertEvent::sole()->message);
    }
}
