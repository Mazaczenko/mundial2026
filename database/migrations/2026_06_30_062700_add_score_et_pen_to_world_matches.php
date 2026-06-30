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
        Schema::table('world_matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('score_home_et')->nullable()->after('result_type');
            $table->unsignedTinyInteger('score_away_et')->nullable()->after('score_home_et');
            $table->unsignedTinyInteger('score_home_pen')->nullable()->after('score_away_et');
            $table->unsignedTinyInteger('score_away_pen')->nullable()->after('score_home_pen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('world_matches', function (Blueprint $table) {
            $table->dropColumn(['score_home_et', 'score_away_et', 'score_home_pen', 'score_away_pen']);
        });
    }
};
