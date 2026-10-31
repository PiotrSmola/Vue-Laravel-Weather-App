<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subskrypcje Web Push (jedno urządzenie / przeglądarka = jeden wiersz).
     */
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();       // sha256 adresu (endpoint bywa za długi na indeks)
            $table->string('public_key');                     // keys.p256dh
            $table->string('auth_token');                     // keys.auth
            $table->string('content_encoding', 16)->default('aes128gcm');
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
