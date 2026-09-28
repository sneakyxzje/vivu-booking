<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_change_proposals', function (Blueprint $table) {
            $table->foreignId('from_schedule_id')->nullable()->constrained('tour_schedules')->nullOnDelete();
            $table->foreignId('to_schedule_id')->nullable()->constrained('tour_schedules')->nullOnDelete();
            $table->json('schedule_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('booking_change_proposals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_schedule_id');
            $table->dropConstrainedForeignId('to_schedule_id');
            $table->dropColumn('schedule_snapshot');
        });
    }
};
