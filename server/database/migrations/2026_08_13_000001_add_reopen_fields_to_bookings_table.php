<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task X07a - Bổ sung các cột phục vụ mở lại đơn đã hủy nhầm trong 24h (Edge Case C06).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('reopen_reason', 500)->nullable()->after('cancellation_plan');
            $table->dateTime('reopened_at')->nullable()->after('reopen_reason');
            $table->foreignId('reopened_by')->nullable()->after('reopened_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn([
                'reopen_reason',
                'reopened_at',
            ]);
        });
    }
};
