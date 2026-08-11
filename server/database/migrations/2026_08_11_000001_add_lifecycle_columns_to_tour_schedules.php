<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A01 — Bổ sung vòng đời chuyến khởi hành.
 *
 * Tài liệu tham chiếu: docs/nghiep-vu/07-thiet-ke-du-lieu.md §1.1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_schedules', function (Blueprint $table) {
            // Thời điểm kết thúc chuyến, tự tính từ start_date + number_of_days của tour.
            $table->dateTime('end_date')->nullable()->after('start_date');

            // Số khách tối thiểu để chuyến được chốt chạy.
            $table->unsignedInteger('min_people')->default(1)->after('max_people');

            // Hạn chốt danh sách. Mặc định start_date trừ 3 ngày.
            $table->dateTime('booking_deadline')->nullable()->after('min_people');

            // Vòng đời: open, closed, confirmed, in_progress, completed, cancelled.
            // Đặt sau guide_id để dễ đọc khi DESCRIBE.
            $table->string('status', 20)->default('open')->after('booking_deadline');

            // Truy vết chốt chuyến.
            $table->dateTime('confirmed_at')->nullable()->after('status');

            // Truy vết hủy chuyến.
            $table->dateTime('cancelled_at')->nullable()->after('confirmed_at');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete()->after('cancelled_at');
            $table->text('cancelled_reason')->nullable()->after('cancelled_by');

            // Chuyến bị ghép vào chuyến nào (task L01 — Ghép chuyến).
            $table->foreignId('merged_into_schedule_id')
                ->nullable()
                ->constrained('tour_schedules')
                ->nullOnDelete()
                ->after('cancelled_reason');

            // Chuyến riêng dành cho một đoàn đặt trọn, khóa bán lẻ.
            $table->boolean('is_private')->default(false)->after('merged_into_schedule_id');
        });

        // Chỉ mục phục vụ danh sách chuyến đang mở bán (query phổ biến nhất).
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->index(['tour_id', 'status', 'start_date'], 'idx_schedules_tour_status_start');

            // Tác vụ nền chốt chuyến qua hạn booking_deadline.
            $table->index(['status', 'booking_deadline'], 'idx_schedules_status_deadline');

            // Kiểm tra trùng lịch hướng dẫn viên.
            $table->index(['guide_id', 'start_date', 'end_date'], 'idx_schedules_guide_dates');
        });
    }

    public function down(): void
    {
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->dropIndex('idx_schedules_guide_dates');
            $table->dropIndex('idx_schedules_status_deadline');
            $table->dropIndex('idx_schedules_tour_status_start');

            $table->dropConstrainedForeignId('merged_into_schedule_id');
            $table->dropConstrainedForeignId('cancelled_by');

            $table->dropColumn([
                'end_date',
                'min_people',
                'booking_deadline',
                'status',
                'confirmed_at',
                'cancelled_at',
                'cancelled_reason',
                'is_private',
            ]);
        });
    }
};
