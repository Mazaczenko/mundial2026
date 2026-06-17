<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_match_id')->constrained('world_matches')->cascadeOnDelete();
            $table->string('player_name');
            $table->enum('team_side', ['home', 'away']);
            $table->string('minute')->nullable();
            $table->enum('card_type', ['yellow', 'red', 'yellow_red']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_cards');
    }
};
