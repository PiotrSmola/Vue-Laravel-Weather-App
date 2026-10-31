<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\Push\PushNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Web Push: klucz publiczny VAPID, rejestracja urządzeń i powiadomienie testowe.
 */
class PushSubscriptionController extends Controller
{
    public function __construct(protected PushNotifier $notifier)
    {
    }

    // GET /api/push/public-key (publiczna)
    public function publicKey()
    {
        if (!$this->notifier->isConfigured()) {
            return response()->json(['error' => 'Powiadomienia push nie są skonfigurowane na serwerze'], 503);
        }

        return response()->json(['public_key' => config('webpush.public_key')]);
    }

    // POST /api/push/subscriptions - zapis (lub przepisanie) subskrypcji tego urządzenia
    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|string|url|starts_with:https://|max:2000',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
            'content_encoding' => 'sometimes|nullable|string|in:aesgcm,aes128gcm',
        ], [
            'endpoint.required' => 'Brak adresu subskrypcji.',
            'endpoint.url' => 'Nieprawidłowy adres subskrypcji.',
            'endpoint.starts_with' => 'Nieprawidłowy adres subskrypcji.',
            'keys.required' => 'Brak kluczy subskrypcji.',
            'keys.p256dh.required' => 'Brak klucza p256dh subskrypcji.',
            'keys.auth.required' => 'Brak klucza auth subskrypcji.',
        ]);

        $user = $request->user();

        // To samo urządzenie po zmianie konta przechodzi na nowego użytkownika
        $subscription = PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])],
            [
                'user_id' => $user->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ]
        );

        // Limit urządzeń: usuwamy najstarsze subskrypcje
        $limit = (int) config('webpush.subscriptions_limit', 10);
        $excess = PushSubscription::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->slice($limit);

        if ($excess->isNotEmpty()) {
            PushSubscription::whereIn('id', $excess)->delete();
        }

        return response()->json([
            'message' => 'Powiadomienia push zostały włączone na tym urządzeniu',
            'id' => $subscription->id,
        ], $subscription->wasRecentlyCreated ? 201 : 200);
    }

    // DELETE /api/push/subscriptions (body lub query: endpoint)
    public function destroy(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|string|max:2000',
        ], [
            'endpoint.required' => 'Brak adresu subskrypcji.',
        ]);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))
            ->delete();

        return response()->json(['message' => 'Powiadomienia push zostały wyłączone na tym urządzeniu']);
    }

    // POST /api/push/test - powiadomienie testowe na wszystkie urządzenia użytkownika
    public function test(Request $request)
    {
        if (!$this->notifier->isConfigured()) {
            return response()->json(['error' => 'Powiadomienia push nie są skonfigurowane na serwerze'], 503);
        }

        $userId = $request->user()->id;

        if (!PushSubscription::where('user_id', $userId)->exists()) {
            return response()->json(['error' => 'Brak urządzeń z włączonymi powiadomieniami push'], 422);
        }

        $counts = $this->notifier->sendToUser($userId, [
            'title' => 'Poland&World Weather',
            'body' => 'To jest powiadomienie testowe. Alerty pogodowe będą wyglądać podobnie.',
            'url' => '/alerts',
            'tag' => 'test',
        ]);

        if ($counts['sent'] === 0) {
            return response()->json(['error' => 'Nie udało się wysłać powiadomienia testowego', 'counts' => $counts], 502);
        }

        return response()->json(['message' => 'Wysłano powiadomienie testowe', 'counts' => $counts]);
    }
}
