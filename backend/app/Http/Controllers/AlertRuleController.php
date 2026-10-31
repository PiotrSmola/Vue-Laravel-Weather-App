<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Models\City;
use App\Services\Alerts\AlertFormatter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reguły alertów pogodowych zalogowanego użytkownika (tylko dla ulubionych miast).
 * Progi przyjmowane i zwracane są w jednostkach metrycznych (°C, m/s, %, hPa).
 */
class AlertRuleController extends Controller
{
    public function __construct(protected AlertFormatter $formatter)
    {
    }

    // GET /api/alerts
    public function index(Request $request)
    {
        $rules = AlertRule::where('user_id', $request->user()->id)
            ->with('city')
            ->orderBy('city_id')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $rules->map(fn (AlertRule $rule) => $this->present($rule))->values(),
            'limit' => (int) config('alerts.rules_limit', 20),
        ]);
    }

    // GET /api/alerts/meta - typy, zjawiska, kanały i limity (dla formularza)
    public function meta()
    {
        return response()->json([
            'types' => collect(AlertRule::types())->map(fn ($type) => [
                'value' => $type,
                'label' => $this->formatter->typeLabel($type),
                'forecast' => in_array($type, AlertRule::FORECAST_TYPES, true),
                'range' => AlertRule::THRESHOLD_RANGES[$type] ?? null,
                'default_threshold' => config('alerts.defaults.' . $type),
            ])->values(),
            'conditions' => collect(AlertRule::CONDITIONS)->map(fn ($c) => [
                'value' => $c,
                'label' => $this->formatter->conditionLabel($c),
            ])->values(),
            'channels' => AlertRule::CHANNELS,
            'limit' => (int) config('alerts.rules_limit', 20),
            'cooldown' => [
                'default' => (int) config('alerts.default_cooldown', 360),
                'min' => (int) config('alerts.min_cooldown', 30),
                'max' => (int) config('alerts.max_cooldown', 10080),
            ],
        ]);
    }

    // POST /api/alerts
    public function store(Request $request)
    {
        $user = $request->user();
        $limit = (int) config('alerts.rules_limit', 20);

        $data = $this->validated($request, null);

        if (AlertRule::where('user_id', $user->id)->count() >= $limit) {
            return response()->json(['error' => 'Osiągnięto limit ' . $limit . ' reguł alertów'], 400);
        }

        $rule = AlertRule::create(array_merge([
            'active' => true,
            'cooldown_minutes' => (int) config('alerts.default_cooldown', 360),
        ], $data, ['user_id' => $user->id]));

        return response()->json(['data' => $this->present($rule->load('city'))], 201);
    }

    // PUT/PATCH /api/alerts/{id} - częściowa aktualizacja
    public function update(Request $request, int $id)
    {
        $rule = $this->findOwn($request, $id);

        if (!$rule) {
            return response()->json(['error' => 'Nie znaleziono reguły alertu'], 404);
        }

        $data = $this->validated($request, $rule);
        $rule->fill($data);

        // Zmiana warunku reguły = nowa reguła: kasujemy cooldown
        if ($rule->isDirty(['type', 'threshold', 'condition', 'city_id'])) {
            $rule->last_triggered_at = null;
        }

        $rule->save();

        return response()->json(['data' => $this->present($rule->load('city'))]);
    }

    // DELETE /api/alerts/{id}
    public function destroy(Request $request, int $id)
    {
        $rule = $this->findOwn($request, $id);

        if (!$rule) {
            return response()->json(['error' => 'Nie znaleziono reguły alertu'], 404);
        }

        $rule->delete();

        return response()->json(['message' => 'Reguła alertu została usunięta']);
    }

    protected function findOwn(Request $request, int $id): ?AlertRule
    {
        return AlertRule::where('user_id', $request->user()->id)->whereKey($id)->first();
    }

    /**
     * Walidacja tworzenia ($rule = null) lub częściowej aktualizacji. Zwraca pola do zapisu,
     * z city_id zamienionym z ID OpenWeather na ID miasta w bazie.
     */
    protected function validated(Request $request, ?AlertRule $rule): array
    {
        $creating = $rule === null;
        $req = $creating ? 'required' : 'sometimes|required';

        $fields = $request->validate([
            'city_id' => $req . '|integer|min:1',
            'type' => [$creating ? 'required' : 'sometimes', 'required', 'string', Rule::in(AlertRule::types())],
            'threshold' => 'sometimes|nullable|numeric',
            'condition' => ['sometimes', 'nullable', 'string', Rule::in(AlertRule::CONDITIONS)],
            'channels' => $req . '|array|min:1',
            'channels.*' => ['string', 'distinct', Rule::in(AlertRule::CHANNELS)],
            'active' => 'sometimes|boolean',
            'cooldown_minutes' => 'sometimes|integer|min:' . (int) config('alerts.min_cooldown', 30)
                . '|max:' . (int) config('alerts.max_cooldown', 10080),
            'label' => 'sometimes|nullable|string|max:100',
        ], [
            'city_id.required' => 'Wybierz miasto.',
            'city_id.integer' => 'Nieprawidłowe miasto.',
            'city_id.min' => 'Nieprawidłowe miasto.',
            'channels.array' => 'Nieprawidłowa lista kanałów powiadomień.',
            'channels.*.distinct' => 'Kanały powiadomień nie mogą się powtarzać.',
            'active.boolean' => 'Pole "aktywny" musi mieć wartość prawda/fałsz.',
            'cooldown_minutes.integer' => 'Odstęp między powiadomieniami musi być liczbą całkowitą minut.',
            'type.required' => 'Wybierz typ alertu.',
            'type.in' => 'Nieznany typ alertu.',
            'threshold.numeric' => 'Próg musi być liczbą.',
            'condition.in' => 'Nieznane zjawisko pogodowe.',
            'channels.required' => 'Wybierz co najmniej jeden kanał powiadomień.',
            'channels.min' => 'Wybierz co najmniej jeden kanał powiadomień.',
            'channels.*.in' => 'Nieznany kanał powiadomień.',
            'cooldown_minutes.min' => 'Odstęp między powiadomieniami musi wynosić co najmniej :min minut.',
            'cooldown_minutes.max' => 'Odstęp między powiadomieniami może wynosić najwyżej :max minut.',
            'label.max' => 'Nazwa może mieć najwyżej :max znaków.',
        ]);

        // Miasto: tylko z ulubionych użytkownika
        if (array_key_exists('city_id', $fields)) {
            $city = City::where('openweather_id', (int) $fields['city_id'])->first();

            if (!$city || !$request->user()->cities()->where('cities.id', $city->id)->exists()) {
                throw ValidationException::withMessages([
                    'city_id' => 'Alerty można ustawić tylko dla miast z ulubionych.',
                ]);
            }

            $fields['city_id'] = $city->id;
        }

        $type = $fields['type'] ?? $rule?->type;
        $threshold = array_key_exists('threshold', $fields) ? $fields['threshold'] : $rule?->threshold;
        $condition = array_key_exists('condition', $fields) ? $fields['condition'] : $rule?->condition;

        if ($type === 'condition') {
            if (!$condition) {
                throw ValidationException::withMessages(['condition' => 'Wybierz zjawisko pogodowe.']);
            }
            $fields['threshold'] = null;
        } else {
            $fields['condition'] = null;
            $optional = in_array($type, AlertRule::FORECAST_TYPES, true);

            if ($threshold === null && !$optional) {
                throw ValidationException::withMessages(['threshold' => 'Podaj próg alertu.']);
            }

            if ($threshold !== null) {
                [$min, $max] = AlertRule::THRESHOLD_RANGES[$type];

                if ((float) $threshold < $min || (float) $threshold > $max) {
                    throw ValidationException::withMessages([
                        'threshold' => "Próg musi mieścić się w zakresie od {$min} do {$max} (jednostki metryczne).",
                    ]);
                }

                $fields['threshold'] = round((float) $threshold, 2);
            }
        }

        if (isset($fields['channels'])) {
            $fields['channels'] = array_values(array_intersect(AlertRule::CHANNELS, $fields['channels']));
        }

        if (array_key_exists('label', $fields)) {
            $fields['label'] = trim((string) $fields['label']) ?: null;
        }

        return $fields;
    }

    // Format odpowiedzi API: city_id = ID OpenWeather (jak w pozostałych trasach)
    protected function present(AlertRule $rule): array
    {
        return [
            'id' => $rule->id,
            'city_id' => $rule->city?->openweather_id,
            'city' => $rule->city ? [
                'id' => $rule->city->openweather_id,
                'name' => $rule->city->name,
                'country' => $rule->city->country,
            ] : null,
            'type' => $rule->type,
            'type_label' => $this->formatter->typeLabel($rule->type),
            'forecast' => $rule->isForecast(),
            'threshold' => $rule->threshold,
            'effective_threshold' => $rule->effectiveThreshold(),
            'condition' => $rule->condition,
            'channels' => array_values((array) $rule->channels),
            'active' => (bool) $rule->active,
            'cooldown_minutes' => (int) $rule->cooldown_minutes,
            'label' => $rule->label,
            'last_triggered_at' => $rule->last_triggered_at?->toIso8601String(),
            'created_at' => $rule->created_at?->toIso8601String(),
        ];
    }
}
