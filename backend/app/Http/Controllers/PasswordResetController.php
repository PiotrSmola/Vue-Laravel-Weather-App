<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

// Przypomnienie hasła (mail z linkiem do frontendu) i ustawienie nowego hasła
class PasswordResetController extends Controller
{
    public const SENT_MESSAGE = 'Jeśli konto z tym adresem e-mail istnieje, wysłaliśmy na nie link do resetowania hasła.';

    // POST /api/forgot-password - zawsze ta sama odpowiedź (brak enumeracji kont)
    public function sendLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        // Wynik (np. brak użytkownika, limit broker'a) celowo nie trafia do klienta
        Password::sendResetLink(['email' => $request->input('email')]);

        return response()->json(['message' => self::SENT_MESSAGE]);
    }

    // POST /api/reset-password
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|max:255',
            'password' => 'required|string|confirmed|min:8',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Po resecie hasła wszystkie sesje są unieważniane
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Nieznany e-mail i zły token dają ten sam komunikat
            return response()->json([
                'message' => __('passwords.token'),
                'errors' => ['email' => [__('passwords.token')]],
            ], 422);
        }

        return response()->json(['message' => __($status)]);
    }
}
