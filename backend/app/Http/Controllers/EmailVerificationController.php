<?php

namespace App\Http\Controllers;

use App\Support\FrontendUrl;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

// Weryfikacja adresu e-mail. Link w mailu prowadzi do frontendu (/verify-email),
// który przekazuje parametry podpisanej trasy backendu do POST /api/email/verify.
class EmailVerificationController extends Controller
{
    // POST /api/email/verify {id, hash, expires, signature}
    public function verify(Request $request)
    {
        $fields = $request->validate([
            'id' => 'required|integer',
            'hash' => 'required|string',
            'expires' => 'required|integer',
            'signature' => 'required|string',
        ]);

        $user = $request->user();

        // Odtworzenie podpisanego adresu trasy verification.verify i sprawdzenie podpisu
        $signedPath = route('verification.verify', ['id' => $fields['id'], 'hash' => $fields['hash']], false)
            . '?' . http_build_query(['expires' => $fields['expires'], 'signature' => $fields['signature']]);

        if (!URL::hasValidSignature(Request::create($signedPath), false)) {
            return response()->json(['error' => 'Link weryfikacyjny jest nieprawidłowy lub wygasł.'], 403);
        }

        // Link musi dotyczyć zalogowanego użytkownika i jego aktualnego adresu e-mail
        if ((int) $fields['id'] !== (int) $user->getKey()
            || !hash_equals(sha1($user->getEmailForVerification()), (string) $fields['hash'])) {
            return response()->json(['error' => 'Ten link weryfikacyjny nie dotyczy Twojego konta lub adres e-mail został zmieniony.'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Adres e-mail był już potwierdzony.',
                'email_verified_at' => $user->email_verified_at,
            ]);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return response()->json([
            'message' => 'Adres e-mail został potwierdzony.',
            'email_verified_at' => $user->fresh()->email_verified_at,
        ]);
    }

    // POST /api/email/verification-notification - ponowna wysyłka linku
    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Adres e-mail jest już potwierdzony.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Wysłaliśmy nowy link weryfikacyjny na adres ' . $user->email . '.']);
    }

    // GET /api/email/verify/{id}/{hash} - nazwana trasa podpisu; otwarta bezpośrednio
    // przekierowuje na stronę frontendu z tymi samymi parametrami
    public function redirectToFrontend(Request $request, string $id, string $hash)
    {
        return redirect()->away(FrontendUrl::to('/verify-email', [
            'id' => $id,
            'hash' => $hash,
            'expires' => $request->query('expires'),
            'signature' => $request->query('signature'),
        ]));
    }
}
