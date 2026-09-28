<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tours', 'is_sandbox')) {
            return;
        }

        // Chỉ dọn tour đã được đánh dấu thử nghiệm, kể cả tour đã xóa mềm.
        // Dùng query builder để không phụ thuộc model sau khi tính năng bị gỡ.
        DB::transaction(function () {
            $tourIds = DB::table('tours')->where('is_sandbox', true)->pluck('id');
            $scheduleIds = DB::table('tour_schedules')->whereIn('tour_id', $tourIds)->pluck('id');

            DB::table('schedule_audit_logs')->whereIn('tour_schedule_id', $scheduleIds)->delete();
            DB::table('ai_knowledge_chunks')->where('source_type', 'tour')->whereIn('source_id', $tourIds)->delete();

            // MySQL chặn xóa tour còn chứng từ, nên dọn rõ từng nhóm dữ liệu thử trước.
            // Khóa ngoại của booking dọn tiếp sổ tiền, hành khách và nhật ký của đơn.
            DB::table('bookings')->whereIn('tour_id', $tourIds)->delete();
            DB::table('reviews')->whereIn('tour_id', $tourIds)->delete();
            DB::table('group_booking_requests')->whereIn('tour_id', $tourIds)->delete();
            DB::table('tours')->whereIn('id', $tourIds)->delete();
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->dropIndex(['is_sandbox']);
            $table->dropColumn('is_sandbox');
        });
    }

    public function down(): void
    {
        // Chỉ khôi phục schema; dữ liệu thử đã xóa không được tái tạo.
        Schema::table('tours', function (Blueprint $table) {
            $table->boolean('is_sandbox')->default(false)->after('is_featured');
            $table->index('is_sandbox');
        });
    }
};
