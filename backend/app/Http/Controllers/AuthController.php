<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            'password' => 'required|confirmed|min:8'
        ]);

        $fields['password'] = Hash::make($fields['password']);

        $user = User::create($fields);

        $token = $user->createToken($user->name ?? 'auth_token');

        return [
            'user' => $user,
            'token' => $token->plainTextToken
        ];
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
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

        $token = $user->createToken($user->name ?? 'auth_token');

        return [
            'user' => $user,
            'token' => $token->plainTextToken
        ];
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
