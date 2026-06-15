<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_matches', function (Blueprint $table) {
            $table->enum('result_type', ['FT', 'AET', 'PEN'])->nullable()->after('score_away');
        });
    }

    public function down(): void
    {
        Schema::table('world_matches', function (Blueprint $table) {
            $table->dropColumn('result_type');
        });
    }
};
