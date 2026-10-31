<?php

namespace App\Http\Controllers;

use App\Models\AlertEvent;
use Illuminate\Http\Request;

/**
 * Lista wyzwolonych alertów (powiadomienia w aplikacji) zalogowanego użytkownika.
 */
class AlertEventController extends Controller
{
    // GET /api/alerts/events?page=1&unread=1
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $events = AlertEvent::where('user_id', $userId)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->with('city')
            ->orderByDesc('triggered_at')
            ->orderByDesc('id')
            ->paginate((int) config('alerts.events_per_page', 15));

        return response()->json([
            'data' => collect($events->items())->map(fn (AlertEvent $event) => $this->present($event))->values(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'unread_count' => $this->unread($userId),
            ],
        ]);
    }

    // GET /api/alerts/events/unread-count
    public function unreadCount(Request $request)
    {
        return response()->json(['count' => $this->unread($request->user()->id)]);
    }

    // POST /api/alerts/events/{id}/read
    public function markRead(Request $request, int $id)
    {
        $event = AlertEvent::where('user_id', $request->user()->id)->whereKey($id)->first();

        if (!$event) {
            return response()->json(['error' => 'Nie znaleziono powiadomienia'], 404);
        }

        if (!$event->read_at) {
            $event->forceFill(['read_at' => now()])->save();
        }

        return response()->json([
            'data' => $this->present($event->load('city')),
            'unread_count' => $this->unread($request->user()->id),
        ]);
    }

    // POST /api/alerts/events/read-all
    public function markAllRead(Request $request)
    {
        $updated = AlertEvent::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['updated' => $updated, 'unread_count' => 0]);
    }

    protected function unread(int $userId): int
    {
        return AlertEvent::where('user_id', $userId)->whereNull('read_at')->count();
    }

    protected function present(AlertEvent $event): array
    {
        return [
            'id' => $event->id,
            'rule_id' => $event->alert_rule_id,
            'type' => $event->type,
            'title' => $event->title,
            'message' => $event->message,
            'value' => $event->value,
            'threshold' => $event->threshold,
            'channels' => array_values((array) $event->channels),
            'city' => $event->city ? [
                'id' => $event->city->openweather_id,
                'name' => $event->city->name,
                'country' => $event->city->country,
            ] : null,
            'triggered_at' => $event->triggered_at?->toIso8601String(),
            'read_at' => $event->read_at?->toIso8601String(),
        ];
    }
}
