<?php

namespace App\Listeners;

use App\Events\WeatherUpdated;
use App\Services\Alerts\AlertEvaluator;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Po zapisaniu nowego pomiaru ocenia reguły "bieżące" (temperatura, wiatr, wilgotność,
 * ciśnienie, zjawisko) dla tego miasta. Działa w kolejce, aby nie spowalniać żądań API.
 * Listener jest wykrywany automatycznie (app/Listeners).
 */
class EvaluateCurrentAlerts implements ShouldQueue
{
    public function __construct(protected AlertEvaluator $evaluator)
    {
    }

    public function handle(WeatherUpdated $event): void
    {
        $this->evaluator->evaluateCurrent($event->city, $event->weatherData);
    }
}
