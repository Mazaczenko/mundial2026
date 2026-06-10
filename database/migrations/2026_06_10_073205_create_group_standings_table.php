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
        Schema::create('group_standings', function (Blueprint $table) {
            $table->id();
            $table->string('group_name', 5);
            $table->integer('api_team_id');
            $table->string('team_name', 100);
            $table->string('team_flag', 255)->nullable();
            $table->tinyInteger('position');
            $table->tinyInteger('played')->default(0);
            $table->tinyInteger('won')->default(0);
            $table->tinyInteger('drawn')->default(0);
            $table->tinyInteger('lost')->default(0);
            $table->tinyInteger('goals_for')->default(0);
            $table->tinyInteger('goals_against')->default(0);
            $table->tinyInteger('points')->default(0);
            $table->timestamp('synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_standings');
    }
};
