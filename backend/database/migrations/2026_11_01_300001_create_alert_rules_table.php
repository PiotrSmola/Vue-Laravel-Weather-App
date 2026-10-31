<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reguły alertów pogodowych użytkownika. Progi zapisywane są w jednostkach metrycznych
     * (°C, m/s, %, hPa), niezależnie od ustawień użytkownika.
     */
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);                          // np. temp_below, condition, frost_forecast
            $table->decimal('threshold', 8, 2)->nullable();      // próg (metryczny)
            $table->string('condition', 20)->nullable();         // dla typu "condition": rain | snow | thunderstorm | fog
            $table->json('channels');                            // ["mail", "push"]
            $table->boolean('active')->default(true);
            $table->unsignedInteger('cooldown_minutes')->default(360);
            $table->string('label', 100)->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['city_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
