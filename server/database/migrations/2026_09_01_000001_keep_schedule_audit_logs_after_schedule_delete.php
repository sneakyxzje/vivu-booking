<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhật ký chuyến phải sống lâu hơn chính chuyến ấy.
 *
 * Bảng dựng ra với `cascadeOnDelete`, trong khi form sửa tour xóa thẳng những chuyến bị bỏ khỏi
 * danh sách mà chưa có khách (`AdminTourController::update`). Một chuyến từng bị dời hạn chốt vài
 * lần rồi tụt về 0 khách - đơn cũ hủy hết và chỗ đã trả về kho - biến mất kéo theo toàn bộ nhật ký
 * của nó. Không ai bấm nút xóa nhật ký nào cả, mà nhật ký vẫn mất.
 *
 * Đổi sang `nullOnDelete`, đúng như `bookings.tour_schedule_id` vẫn làm: dòng nhật ký giữ nguyên
 * ai sửa, lúc nào, từ mốc nào sang mốc nào và vì sao - đủ trả lời khiếu nại kể cả khi chuyến không
 * còn trên hệ thống.
 *
 * Xem docs/nghiep-vu/16-sua-han-chot.md mục 9.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_audit_logs', function (Blueprint $table) {
            $table->dropForeign(['tour_schedule_id']);
            $table->foreignId('tour_schedule_id')->nullable()->change();
            $table->foreign('tour_schedule_id')
                ->references('id')
                ->on('tour_schedules')
                ->nullOnDelete();
        });
    }

    /**
     * Cố ý không có đường lùi.
     *
     * Hạ về `cascadeOnDelete` đòi cột phải NOT NULL trở lại, tức phải xóa những dòng đang trỏ vào
     * chuyến đã mất - đúng thứ migration này sinh ra để cứu. Một `down()` làm mất dữ liệu thì thà
     * không có.
     */
    public function down(): void
    {
    }
};
