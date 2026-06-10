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
        Schema::create('world_matches', function (Blueprint $table) {
            $table->id();
            $table->integer('api_fixture_id')->unique();
            $table->string('home_team', 100);
            $table->string('away_team', 100);
            $table->string('home_team_flag', 255)->nullable();
            $table->string('away_team_flag', 255)->nullable();
            $table->timestamp('kickoff_at');
            $table->enum('stage', ['group', 'r32', 'r16', 'qf', 'sf', 'final']);
            $table->string('group_name', 5)->nullable();
            $table->enum('status', ['scheduled', 'finished'])->default('scheduled');
            $table->unsignedTinyInteger('score_home')->nullable();
            $table->unsignedTinyInteger('score_away')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('world_matches');
    }
};
