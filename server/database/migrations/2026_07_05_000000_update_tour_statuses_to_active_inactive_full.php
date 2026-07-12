<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE tours SET status = 'active' WHERE status = 'pending'");
        DB::statement("ALTER TABLE tours MODIFY status ENUM('active', 'inactive', 'full') NOT NULL DEFAULT 'active'");

        DB::statement("UPDATE tour_schedules SET status = 'inactive' WHERE status IN ('canceled', 'completed')");
        DB::statement("ALTER TABLE tour_schedules MODIFY status ENUM('active', 'inactive', 'full') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE tours MODIFY status ENUM('pending', 'active', 'inactive') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE tour_schedules MODIFY status ENUM('active', 'canceled', 'completed') NOT NULL DEFAULT 'active'");
    }
};
