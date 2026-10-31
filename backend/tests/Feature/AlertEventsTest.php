<?php

namespace Tests\Feature;

use App\Models\AlertEvent;
use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesAlerts;
use Tests\TestCase;

class AlertEventsTest extends TestCase
{
    use RefreshDatabase, CreatesAlerts;

    protected function makeEvent(User $user, City $city, array $attributes = []): AlertEvent
    {
        return AlertEvent::create(array_merge([
            'user_id' => $user->id,
            'city_id' => $city->id,
            'type' => 'temp_below',
            'value' => -3,
            'threshold' => 0,
            'title' => 'Rzeszów: Temperatura poniżej progu',
            'message' => 'Temperatura w mieście Rzeszów spadła do -3°C (próg: 0°C).',
            'channels' => ['mail'],
            'triggered_at' => now(),
        ], $attributes));
    }

    public function test_events_are_paginated_newest_first_with_unread_count(): void
    {
        [$user, $city] = $this->userWithFavorite();
        [$other, $otherCity] = $this->userWithFavorite(756135);

        for ($i = 0; $i < 17; $i++) {
            $this->makeEvent($user, $city, ['triggered_at' => now()->subMinutes(100 - $i), 'title' => "Alert {$i}"]);
        }
        $this->makeEvent($user, $city, ['read_at' => now(), 'triggered_at' => now()->subDay()]);
        $this->makeEvent($other, $otherCity);

        Sanctum::actingAs($user);

        $this->getJson('/api/alerts/events')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('data.0.title', 'Alert 16')
            ->assertJsonPath('data.0.city.id', 759734)
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonPath('meta.total', 18)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.unread_count', 17);

        $this->getJson('/api/alerts/events?page=2')->assertJsonCount(3, 'data');
        $this->getJson('/api/alerts/events?unread=1')->assertJsonPath('meta.total', 17);
        $this->getJson('/api/alerts/events/unread-count')->assertOk()->assertExactJson(['count' => 17]);
    }

    public function test_mark_single_event_as_read(): void
    {
        [$user, $city] = $this->userWithFavorite();
        $event = $this->makeEvent($user, $city);
        $this->makeEvent($user, $city);
        Sanctum::actingAs($user);

        $this->postJson("/api/alerts/events/{$event->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('unread_count', 1);

        $this->assertNotNull($event->fresh()->read_at);
    }

    public function test_mark_all_as_read(): void
    {
        [$user, $city] = $this->userWithFavorite();
        [$other, $otherCity] = $this->userWithFavorite(756135);
        $this->makeEvent($user, $city);
        $this->makeEvent($user, $city);
        $foreign = $this->makeEvent($other, $otherCity);
        Sanctum::actingAs($user);

        $this->postJson('/api/alerts/events/read-all')->assertOk()->assertJsonPath('updated', 2);

        $this->getJson('/api/alerts/events/unread-count')->assertExactJson(['count' => 0]);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_cannot_read_events_of_other_users(): void
    {
        [$owner, $city] = $this->userWithFavorite();
        $event = $this->makeEvent($owner, $city);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/alerts/events/{$event->id}/read")
            ->assertNotFound()
            ->assertJsonPath('error', 'Nie znaleziono powiadomienia');
        $this->getJson('/api/alerts/events')->assertJsonCount(0, 'data');
        $this->assertNull($event->fresh()->read_at);
    }

    public function test_events_require_authentication(): void
    {
        $this->getJson('/api/alerts/events/unread-count')->assertUnauthorized();
        $this->postJson('/api/alerts/events/read-all')->assertUnauthorized();
        $this->postJson('/api/alerts/events/1/read')->assertUnauthorized();
    }

    public function test_deleting_rule_keeps_events(): void
    {
        [$user, $city] = $this->userWithFavorite();
        $rule = $this->makeRule($user, $city);
        $event = $this->makeEvent($user, $city, ['alert_rule_id' => $rule->id]);

        $rule->delete();

        $this->assertNull($event->fresh()->alert_rule_id);
    }
}
