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
        Schema::table('booking_change_proposals', function (Blueprint $table) {
            $table->dateTime('proposed_date')->nullable()->after('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_change_proposals', function (Blueprint $table) {
            $table->dropColumn('proposed_date');
        });
    }
};
