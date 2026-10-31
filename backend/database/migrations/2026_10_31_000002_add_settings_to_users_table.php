<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ustawienia użytkownika: jednostki, jednostka wiatru, język, miasto domowe.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('units', 16)->default('metric');      // metric | imperial
            $table->string('wind_unit', 8)->default('kmh');      // ms | kmh | mph
            $table->string('lang', 8)->default('pl');            // pl | en
            $table->unsignedBigInteger('home_city_id')->nullable(); // ID miasta w OpenWeather (nie klucz obcy)
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['units', 'wind_unit', 'lang', 'home_city_id']);
        });
    }
};
