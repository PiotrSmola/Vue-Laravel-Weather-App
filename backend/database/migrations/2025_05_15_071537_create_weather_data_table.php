<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('weather_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->onDelete('cascade');
            $table->decimal('temperature', 5, 2);
            $table->decimal('feels_like', 5, 2);
            $table->unsignedTinyInteger('humidity');
            $table->unsignedSmallInteger('pressure');
            $table->decimal('wind_speed', 5, 2);
            $table->unsignedSmallInteger('wind_direction');
            $table->string('weather_condition');
            $table->string('weather_description');
            $table->string('weather_icon', 10);
            $table->unsignedTinyInteger('clouds');
            $table->unsignedInteger('visibility');
            $table->datetime('sunrise');
            $table->datetime('sunset');
            $table->datetime('measured_at');
            $table->json('data_json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_data');
    }
};