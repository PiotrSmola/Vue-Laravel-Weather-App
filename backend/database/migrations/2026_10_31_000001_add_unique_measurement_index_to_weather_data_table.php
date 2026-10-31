<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Usuwa zduplikowane pomiary i dodaje unikalny indeks (city_id, measured_at).
     */
    public function up(): void
    {
        // Duplikaty: zostawiamy najstarszy wiersz (najmniejsze id) z każdej grupy
        $duplicates = DB::table('weather_data')
            ->select('city_id', 'measured_at', DB::raw('MIN(id) as keep_id'))
            ->groupBy('city_id', 'measured_at')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('weather_data')
                ->where('city_id', $duplicate->city_id)
                ->where('measured_at', $duplicate->measured_at)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('weather_data', function (Blueprint $table) {
            $table->unique(['city_id', 'measured_at'], 'weather_data_city_measured_unique');
            $table->index('measured_at', 'weather_data_measured_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('weather_data', function (Blueprint $table) {
            $table->dropIndex('weather_data_measured_at_index');
            $table->dropUnique('weather_data_city_measured_unique');
        });
    }
};
