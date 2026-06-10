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
        Schema::create('bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('world_matches')->cascadeOnDelete();
            $table->enum('prediction_1x2', ['1', 'X', '2']);
            $table->unsignedTinyInteger('predicted_home')->nullable();
            $table->unsignedTinyInteger('predicted_away')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamps();
            $table->unique(['participant_id', 'match_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bets');
    }
};
