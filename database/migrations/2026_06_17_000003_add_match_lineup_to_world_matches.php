<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_matches', function (Blueprint $table) {
            $table->json('match_lineup')->nullable()->after('match_stats');
        });
    }

    public function down(): void
    {
        Schema::table('world_matches', function (Blueprint $table) {
            $table->dropColumn('match_lineup');
        });
    }
};
