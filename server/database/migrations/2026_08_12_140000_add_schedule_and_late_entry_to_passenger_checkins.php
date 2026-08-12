<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung hai cột còn thiếu so với docs/nghiep-vu/07-thiet-ke-du-lieu.md mục 1.6.
 *
 * tour_schedule_id: điểm dừng thuộc LỊCH TRÌNH CỦA TOUR, không thuộc chuyến khởi hành. Nên nếu
 * không lưu chuyến ở đây thì mọi câu hỏi dạng "điểm danh của chuyến 20/08" đều phải join hai
 * tầng qua booking_passengers rồi bookings. Quan trọng hơn, quy tắc kiểm tra của H08 cần biết
 * hành khách này có thuộc đúng chuyến đang điểm danh hay không, kiểm tra đó phải rẻ.
 *
 * is_late_entry: quy tắc ở tài liệu 04 mục 5.3 cho phép ghi bù khi hướng dẫn viên mất sóng,
 * nhưng ghi bù quá 24 giờ thì phải đánh dấu để điều hành truy vết được. Không có cột này thì
 * quy tắc đó không cài được.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passenger_checkins', function (Blueprint $table) {
            $table->foreignId('tour_schedule_id')
                ->nullable()
                ->after('booking_passenger_id')
                ->constrained('tour_schedules')
                ->cascadeOnDelete();

            $table->boolean('is_late_entry')
                ->default(false)
                ->after('checked_at');
        });

        // Điền chuyến cho các bản ghi đã có, suy từ đơn của hành khách.
        // Bảng hiện chưa có dữ liệu thật nhưng vẫn viết để migration đúng trong mọi trường hợp.
        DB::statement('
            UPDATE passenger_checkins
            SET tour_schedule_id = (
                SELECT bookings.tour_schedule_id
                FROM booking_passengers
                JOIN bookings ON bookings.id = booking_passengers.booking_id
                WHERE booking_passengers.id = passenger_checkins.booking_passenger_id
            )
            WHERE tour_schedule_id IS NULL
        ');

        Schema::table('passenger_checkins', function (Blueprint $table) {
            $table->index(['tour_schedule_id', 'itinerary_checkpoint_id'], 'idx_checkins_schedule_checkpoint');
        });
    }

    public function down(): void
    {
        Schema::table('passenger_checkins', function (Blueprint $table) {
            $table->dropIndex('idx_checkins_schedule_checkpoint');
            $table->dropConstrainedForeignId('tour_schedule_id');
            $table->dropColumn('is_late_entry');
        });
    }
};
