<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'units',
        'wind_unit',
        'lang',
        'home_city_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Domyślne ustawienia (takie same jak w migracji)
    protected $attributes = [
        'units' => 'metric',
        'wind_unit' => 'kmh',
        'lang' => 'pl',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'home_city_id' => 'integer',
        ];
    }

    public function cities()
    {
        return $this->belongsToMany(City::class, 'user_cities')->withTimestamps();
    }

    // Ustawienia w formacie kontraktu API: {units, wind_unit, lang, home_city_id}
    protected function settings(): Attribute
    {
        return Attribute::get(fn () => [
            'units' => $this->units ?? 'metric',
            'wind_unit' => $this->wind_unit ?? 'kmh',
            'lang' => $this->lang ?? 'pl',
            'home_city_id' => $this->home_city_id,
        ]);
    }
}
