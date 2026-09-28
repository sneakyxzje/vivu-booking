<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->dateTime('demo_time')->nullable();
            $table->dateTime('demo_time_set_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->dropColumn(['demo_time', 'demo_time_set_at']);
        });
    }
};
