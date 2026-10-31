<?php

namespace App\Notifications;

use App\Models\AlertEvent;
use App\Notifications\Channels\WebPushChannel;
use App\Services\Alerts\AlertFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Powiadomienie o wyzwolonym alercie pogodowym (mail i/lub Web Push, wg kanałów reguły).
 */
class AlertTriggered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AlertEvent $event)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = [];

        foreach ((array) $this->event->channels as $channel) {
            match ($channel) {
                'mail' => $channels[] = 'mail',
                'push' => $channels[] = WebPushChannel::class,
                default => null,
            };
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->event->loadMissing('city', 'rule');
        $formatter = app(AlertFormatter::class);

        return (new MailMessage)
            ->subject('Alert pogodowy: ' . $event->title)
            ->markdown('mail.alerts.triggered', [
                'event' => $event,
                'userName' => $notifiable->name ?? null,
                'typeLabel' => $formatter->typeLabel($event->type),
                'ruleSummary' => $event->rule ? $formatter->ruleSummary($event->rule, $notifiable) : null,
                'value' => $event->value !== null ? $formatter->formatValue($event->type, $event->value, $notifiable) : null,
                'threshold' => $event->threshold !== null ? $formatter->formatValue($event->type, $event->threshold, $notifiable) : null,
                'conditionLabel' => $event->type === 'condition' ? ($event->data['description'] ?? $formatter->conditionLabel($event->data['condition'] ?? null)) : null,
                'cityUrl' => config('alerts.frontend_url') . $event->cityPath(),
                'alertsUrl' => config('alerts.frontend_url') . '/alerts',
            ]);
    }

    // Payload odbierany przez service worker (src/sw.js)
    public function toWebPush(object $notifiable): array
    {
        $event = $this->event->loadMissing('city');

        return [
            'title' => $event->title,
            'body' => $event->message,
            'url' => $event->cityPath(),
            'tag' => 'alert-rule-' . ($event->alert_rule_id ?? $event->id),
            'event_id' => $event->id,
        ];
    }
}
