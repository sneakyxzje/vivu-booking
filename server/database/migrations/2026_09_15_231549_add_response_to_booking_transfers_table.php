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
        Schema::table('booking_transfers', function (Blueprint $table) {
            $table->enum('response', ['pending', 'accepted', 'rejected'])->default('pending')->after('approved_at');
            $table->timestamp('responded_at')->nullable()->after('response');
            $table->string('response_token')->unique()->nullable()->after('responded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_transfers', function (Blueprint $table) {
            $table->dropColumn(['response', 'responded_at', 'response_token']);
        });
    }
};
