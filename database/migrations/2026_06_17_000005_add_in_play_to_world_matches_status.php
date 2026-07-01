<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE world_matches MODIFY COLUMN status ENUM('scheduled', 'in_play', 'finished') NOT NULL DEFAULT 'scheduled'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE world_matches MODIFY COLUMN status ENUM('scheduled', 'finished') NOT NULL DEFAULT 'scheduled'");
    }
};
