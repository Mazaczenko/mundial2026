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
        Schema::create('match_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_match_id')->constrained('world_matches')->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('player_name');
            $table->enum('team_side', ['home', 'away']);
            $table->unsignedSmallInteger('minute')->nullable();
            $table->boolean('own_goal')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_goals');
    }
};
