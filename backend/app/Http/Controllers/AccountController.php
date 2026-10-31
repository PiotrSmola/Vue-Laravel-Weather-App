<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

// Zarządzanie kontem: profil, hasło, aktywne sesje (tokeny), eksport danych, usunięcie konta
class AccountController extends Controller
{
    // GET /api/account
    public function show(Request $request)
    {
        return response()->json($this->accountPayload($request->user()));
    }

    // PUT /api/account/profile - zmiana e-maila wymaga ponownej weryfikacji
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $fields = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
        ]);

        $emailChanged = mb_strtolower($fields['email']) !== mb_strtolower($user->email);

        $user->name = $fields['name'];
        $user->email = $fields['email'];

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(array_merge($this->accountPayload($user->fresh()), [
            'message' => $emailChanged
                ? 'Profil został zapisany. Wysłaliśmy link weryfikacyjny na nowy adres e-mail.'
                : 'Profil został zapisany.',
        ]));
    }

    // PUT /api/account/password - po zmianie pozostałe sesje są wylogowywane
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string|current_password:sanctum',
            'password' => 'required|string|confirmed|min:8|different:current_password',
        ]);

        $user = $request->user();
        $user->forceFill(['password' => Hash::make($request->input('password'))])->save();

        $currentId = $this->currentTokenId($request);
        $user->tokens()
            ->when($currentId, fn ($query) => $query->whereKeyNot($currentId))
            ->delete();

        return response()->json([
            'message' => 'Hasło zostało zmienione. Pozostałe urządzenia zostały wylogowane.',
        ]);
    }

    // GET /api/account/tokens - aktywne sesje (urządzenia)
    public function tokens(Request $request)
    {
        $currentId = $this->currentTokenId($request);

        $tokens = $request->user()->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'expires_at' => $token->expires_at,
                'current' => $token->id === $currentId,
            ])
            ->values();

        return response()->json($tokens);
    }

    // DELETE /api/account/tokens/{id} - tylko własne tokeny
    public function destroyToken(Request $request, int $tokenId)
    {
        $token = $request->user()->tokens()->whereKey($tokenId)->first();

        if (!$token) {
            return response()->json(['error' => 'Nie znaleziono sesji'], 404);
        }

        $token->delete();

        return response()->json(['message' => 'Sesja została zakończona.']);
    }

    // GET /api/account/export - dane konta w formacie JSON (do pobrania)
    public function export(Request $request)
    {
        $user = $request->user();

        $favorites = $user->cities()->orderBy('user_cities.created_at')->get()->map(fn ($city) => [
            'openweather_id' => $city->openweather_id,
            'name' => $city->name,
            'country' => $city->country,
            'latitude' => $city->latitude,
            'longitude' => $city->longitude,
            'added_at' => $city->pivot->created_at,
        ])->values();

        $data = [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'settings' => $user->settings,
            'favorites' => $favorites,
            'sessions' => $user->tokens()->get()->map(fn ($token) => [
                'name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
            ])->values(),
        ];

        $filename = 'konto-' . $user->id . '-' . now()->format('Y-m-d') . '.json';

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // DELETE /api/account - wymaga potwierdzenia hasłem
    public function destroy(Request $request)
    {
        $request->validate([
            'password' => 'required|string|current_password:sanctum',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->cities()->detach();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            // Pozostałe tabele powiązane z użytkownikiem mają klucze obce z ON DELETE CASCADE
            $user->delete();
        });

        return response()->json(['message' => 'Konto zostało usunięte.']);
    }

    private function accountPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'settings' => $user->settings,
            'stats' => [
                'favorites_count' => $user->cities()->count(),
                'sessions_count' => $user->tokens()->count(),
            ],
        ];
    }

    private function currentTokenId(Request $request): ?int
    {
        $token = $request->user()->currentAccessToken();

        return $token instanceof PersonalAccessToken ? (int) $token->id : null;
    }
}
