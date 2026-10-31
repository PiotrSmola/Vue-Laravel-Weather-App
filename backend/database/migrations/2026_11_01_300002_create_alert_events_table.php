<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wyzwolone alerty (lista powiadomień w aplikacji).
     */
    public function up(): void
    {
        Schema::create('alert_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alert_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->decimal('value', 10, 2)->nullable();      // zmierzona / prognozowana wartość (metryczna)
            $table->decimal('threshold', 8, 2)->nullable();
            $table->string('title');
            $table->text('message');
            $table->json('channels');
            $table->json('data')->nullable();
            $table->string('dedupe_key', 100)->nullable();   // np. data prognozy - to samo zdarzenie nie jest zgłaszane dwa razy
            $table->timestamp('triggered_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_events');
    }
};
