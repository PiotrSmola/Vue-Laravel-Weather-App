<?php

namespace App\Notifications;

use App\Support\FrontendUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

// Mail z linkiem do resetowania hasła - link prowadzi do frontendu (/reset-password)
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function resetUrl($notifiable): string
    {
        return FrontendUrl::to('/reset-password', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        $expire = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

        return (new MailMessage)
            ->subject('Resetowanie hasła')
            ->greeting('Witaj!')
            ->line('Otrzymujesz tę wiadomość, ponieważ ktoś poprosił o zresetowanie hasła do Twojego konta.')
            ->action('Ustaw nowe hasło', $this->resetUrl($notifiable))
            ->line("Link wygaśnie za {$expire} minut.")
            ->line('Jeśli to nie Ty prosiłeś o zmianę hasła, zignoruj tę wiadomość - hasło pozostanie bez zmian.')
            ->salutation('Pozdrawiamy, zespół Poland&World Weather');
    }
}
