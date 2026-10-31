<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolejność ulubionych miast (position) i własna nazwa (alias, np. "Dom", "Działka").
     */
    public function up(): void
    {
        Schema::table('user_cities', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('city_id');
            $table->string('alias', 50)->nullable()->after('position');
        });

        // Istniejące ulubione: kolejność wg daty dodania (osobno dla każdego użytkownika)
        $rows = DB::table('user_cities')->orderBy('user_id')->orderBy('created_at')->orderBy('id')->get(['id', 'user_id']);
        $positions = [];

        foreach ($rows as $row) {
            $positions[$row->user_id] = ($positions[$row->user_id] ?? -1) + 1;
            DB::table('user_cities')->where('id', $row->id)->update(['position' => $positions[$row->user_id]]);
        }
    }

    public function down(): void
    {
        Schema::table('user_cities', function (Blueprint $table) {
            $table->dropColumn(['position', 'alias']);
        });
    }
};
