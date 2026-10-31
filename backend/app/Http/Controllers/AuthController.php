<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|confirmed|min:8',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => Hash::make($fields['password']),
        ]);

        // Mail z linkiem weryfikacyjnym (listener Laravela, wysyłka przez kolejkę)
        event(new Registered($user));

        $token = $user->createToken($this->deviceName($request));

        return [
            'user' => $user,
            'token' => $token->plainTextToken
        ];
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        // Ten sam komunikat dla nieznanego e-maila i złego hasła (brak enumeracji kont)
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'errors' => [
                    'email' => ['Nieprawidłowe dane logowania.']
                ]
            ], 401);
        }

        $token = $user->createToken($this->deviceName($request));

        return [
            'user' => $user,
            'token' => $token->plainTextToken
        ];
    }

    // Nazwa tokenu = urządzenie (lista aktywnych sesji w /api/account/tokens)
    private function deviceName(Request $request): string
    {
        $name = trim((string) $request->input('device_name'));

        return $name !== '' ? $name : 'web';
    }

    // Wylogowanie z bieżącego urządzenia (usuwa tylko bieżący token)
    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return [
            'message' => 'Wylogowano pomyślnie'
        ];
    }

    // Wylogowanie ze wszystkich urządzeń (usuwa wszystkie tokeny użytkownika)
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return [
            'message' => 'Wylogowano ze wszystkich urządzeń'
        ];
    }
}
