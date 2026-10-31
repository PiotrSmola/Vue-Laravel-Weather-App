<?php

namespace App\Notifications;

use App\Support\FrontendUrl;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

// Mail weryfikujący adres e-mail. Podpisany adres trasy backendu (verification.verify)
// jest przepisywany na stronę frontendu /verify-email z tymi samymi parametrami.
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    protected function verificationUrl($notifiable): string
    {
        // Podpis względny (bez hosta) - weryfikacja nie zależy od APP_URL ani nagłówka Host
        $signed = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
            false
        );

        parse_str((string) parse_url($signed, PHP_URL_QUERY), $query);

        return FrontendUrl::to('/verify-email', [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
            'expires' => $query['expires'] ?? null,
            'signature' => $query['signature'] ?? null,
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        $expire = (int) config('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject('Potwierdź adres e-mail')
            ->greeting('Witaj, ' . $notifiable->name . '!')
            ->line('Kliknij poniższy przycisk, aby potwierdzić swój adres e-mail.')
            ->action('Potwierdź adres e-mail', $this->verificationUrl($notifiable))
            ->line("Link wygaśnie za {$expire} minut. Aby go użyć, musisz być zalogowany.")
            ->line('Jeśli nie zakładałeś konta, zignoruj tę wiadomość.')
            ->salutation('Pozdrawiamy, zespół Poland&World Weather');
    }
}
