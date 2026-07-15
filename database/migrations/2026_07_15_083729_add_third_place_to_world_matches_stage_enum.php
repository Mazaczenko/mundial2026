<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE world_matches MODIFY COLUMN stage ENUM('group','r32','r16','qf','sf','3rd_place','final') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE world_matches MODIFY COLUMN stage ENUM('group','r32','r16','qf','sf','final') NOT NULL");
    }
};
