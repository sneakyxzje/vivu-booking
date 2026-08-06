<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('adult_count')->default(1)->after('guests');
            $table->unsignedInteger('child_count')->default(0)->after('adult_count');
            $table->unsignedInteger('infant_count')->default(0)->after('child_count');
        });

        DB::table('bookings')->update([
            'adult_count' => DB::raw('guests'),
            'child_count' => 0,
            'infant_count' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['adult_count', 'child_count', 'infant_count']);
        });
    }
};