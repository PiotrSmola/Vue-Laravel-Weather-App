<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historia pomiarów jakości powietrza (OpenWeather Air Pollution API).
     * Stężenia składników w µg/m³, aqi w skali OpenWeather 1-5.
     */
    public function up(): void
    {
        Schema::create('air_quality_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('aqi');
            $table->decimal('co', 10, 2)->nullable();
            $table->decimal('no', 10, 2)->nullable();
            $table->decimal('no2', 10, 2)->nullable();
            $table->decimal('o3', 10, 2)->nullable();
            $table->decimal('so2', 10, 2)->nullable();
            $table->decimal('pm2_5', 10, 2)->nullable();
            $table->decimal('pm10', 10, 2)->nullable();
            $table->decimal('nh3', 10, 2)->nullable();
            $table->datetime('measured_at');
            $table->timestamps();

            $table->unique(['city_id', 'measured_at'], 'air_quality_data_city_measured_unique');
            $table->index('measured_at', 'air_quality_data_measured_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('air_quality_data');
    }
};
