<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE world_matches MODIFY COLUMN status ENUM('scheduled', 'in_play', 'finished') NOT NULL DEFAULT 'scheduled'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE world_matches MODIFY COLUMN status ENUM('scheduled', 'finished') NOT NULL DEFAULT 'scheduled'");
    }
};
