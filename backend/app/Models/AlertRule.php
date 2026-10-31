<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reguła alertu pogodowego. Próg zawsze w jednostkach metrycznych:
 * temperatura °C, wiatr m/s, wilgotność %, ciśnienie hPa, prawdopodobieństwo opadów %.
 */
class AlertRule extends Model
{
    // Typy oceniane przy każdym nowym pomiarze (zdarzenie WeatherUpdated)
    public const CURRENT_TYPES = [
        'temp_below',
        'temp_above',
        'wind_above',
        'humidity_above',
        'pressure_below',
        'condition',
    ];

    // Typy oceniane na podstawie prognozy (komenda alerts:forecast)
    public const FORECAST_TYPES = [
        'rain_forecast_tomorrow',
        'frost_forecast',
    ];

    public const CONDITIONS = ['rain', 'snow', 'thunderstorm', 'fog'];

    public const CHANNELS = ['mail', 'push'];

    // Zakresy progów (metryczne) dla walidacji
    public const THRESHOLD_RANGES = [
        'temp_below' => [-80, 60],
        'temp_above' => [-80, 60],
        'wind_above' => [0, 100],
        'humidity_above' => [0, 100],
        'pressure_below' => [850, 1100],
        'rain_forecast_tomorrow' => [1, 100],
        'frost_forecast' => [-50, 10],
    ];

    protected $fillable = [
        'user_id',
        'city_id',
        'type',
        'threshold',
        'condition',
        'channels',
        'active',
        'cooldown_minutes',
        'label',
        'last_triggered_at',
    ];

    protected $attributes = [
        'active' => true,
        'cooldown_minutes' => 360,
    ];

    protected $casts = [
        'threshold' => 'float',
        'channels' => 'array',
        'active' => 'boolean',
        'cooldown_minutes' => 'integer',
        'last_triggered_at' => 'datetime',
    ];

    public static function types(): array
    {
        return array_merge(self::CURRENT_TYPES, self::FORECAST_TYPES);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function events()
    {
        return $this->hasMany(AlertEvent::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    // Tylko reguły dla miast, które użytkownik nadal ma w ulubionych
    public function scopeForFavoriteCities(Builder $query): Builder
    {
        return $query->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('user_cities')
                ->whereColumn('user_cities.user_id', 'alert_rules.user_id')
                ->whereColumn('user_cities.city_id', 'alert_rules.city_id');
        });
    }

    public function isForecast(): bool
    {
        return in_array($this->type, self::FORECAST_TYPES, true);
    }

    // Próg z uwzględnieniem wartości domyślnych dla alertów prognozy
    public function effectiveThreshold(): ?float
    {
        if ($this->threshold !== null) {
            return (float) $this->threshold;
        }

        $default = config('alerts.defaults.' . $this->type);

        return $default !== null ? (float) $default : null;
    }

    public function isCoolingDown(?Carbon $now = null): bool
    {
        if (!$this->last_triggered_at) {
            return false;
        }

        $now ??= now();

        return $this->last_triggered_at->copy()->addMinutes($this->cooldown_minutes)->greaterThan($now);
    }

    /**
     * Atomowo rezerwuje wyzwolenie reguły (ustawia last_triggered_at), o ile minął cooldown.
     * Zwraca false, gdy inny proces zdążył już wyzwolić regułę - dzięki temu nie ma duplikatów.
     */
    public function claimTrigger(?Carbon $now = null): bool
    {
        $now ??= now();
        $cutoff = $now->copy()->subMinutes($this->cooldown_minutes);

        $updated = static::whereKey($this->getKey())
            ->where('active', true)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_triggered_at')->orWhere('last_triggered_at', '<=', $cutoff);
            })
            ->update(['last_triggered_at' => $now]);

        if ($updated === 1) {
            $this->last_triggered_at = $now;
            $this->syncOriginalAttribute('last_triggered_at');

            return true;
        }

        return false;
    }
}
