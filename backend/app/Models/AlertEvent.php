<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Wyzwolony alert - pozycja na liście powiadomień w aplikacji.
 */
class AlertEvent extends Model
{
    protected $fillable = [
        'user_id',
        'alert_rule_id',
        'city_id',
        'type',
        'value',
        'threshold',
        'title',
        'message',
        'channels',
        'data',
        'dedupe_key',
        'triggered_at',
        'read_at',
    ];

    protected $casts = [
        'value' => 'float',
        'threshold' => 'float',
        'channels' => 'array',
        'data' => 'array',
        'triggered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rule()
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // Ścieżka strony miasta we frontendzie (np. /weather/759734)
    public function cityPath(): string
    {
        return '/weather/' . ($this->city?->openweather_id ?? '');
    }
}
