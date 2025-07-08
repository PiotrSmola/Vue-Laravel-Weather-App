<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed'
        ]);

        $fields['password'] = Hash::make($fields['password']);

        $user = User::create($fields);

        $token = $user->createToken($request->name ?? 'auth_token');

        return [
            'user' => $user,
            'token' => $token->plainTextToken
        ];
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users',
                'password' => 'required'
            ]);

            $user = User::where('email', $request->email)->first();

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
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Wystąpił błąd podczas próby logowania',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            // Usuwanie wszystkich tokenów użytkownika
            $request->user()->tokens()->delete();

            return [
                'message' => 'Wylogowano pomyślnie'
            ];
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Wystąpił błąd podczas wylogowywania',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}