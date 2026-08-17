<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hướng dẫn viên xin được bàn giao đoàn.
 *
 * Trước đó chỉ điều hành bàn giao được, và chính điều hành là người viết "tình trạng đoàn" - dù
 * họ đang ngồi văn phòng và không chứng kiến gì. Trên thực tế họ phải gọi điện hỏi rồi gõ lại,
 * tức thông tin đi vòng qua một người không có mặt, và đó đúng là chỗ nội dung bị mất mát.
 *
 * Chia lại theo đúng ai biết cái gì:
 *
 *   - **Hướng dẫn viên** viết lý do và tình trạng đoàn. Chỉ họ biết đoàn đang ở đâu, ai chưa
 *     điểm danh, khách nào cần để ý.
 *   - **Điều hành** chọn người thay. Tìm ai đang rảnh là việc xếp lịch, cần nhìn toàn bộ lịch
 *     công ty.
 *
 * Vì thế yêu cầu ở đây **không có cột người thay**: hướng dẫn viên nói "tôi cần được thay", không
 * phải "giao cho anh B".
 *
 * Cùng khuôn với yêu cầu hủy đơn của khách (`booking_change_requests`): người chịu ảnh hưởng
 * khởi xướng, người có thẩm quyền quyết.
 *
 * Xem docs/nghiep-vu/04-luong-dieu-hanh.md mục 4.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_handover_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tour_schedule_id')->constrained('tour_schedules')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();

            $table->string('status', 20)->default('pending');

            $table->string('reason', 255);

            /** Tình trạng đoàn do chính người đang dẫn viết. Phần có giá trị nhất của yêu cầu. */
            $table->text('group_state');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            /*
             * Biên bản sinh ra khi duyệt.
             *
             * Duyệt KHÔNG tự thực hiện bàn giao mà gọi đúng đường bàn giao chung, rồi trỏ vào kết
             * quả ở đây. Hai đường ghi cho cùng một việc là khuôn của phần lớn lỗi ở dự án này;
             * cột này là chỗ chứng minh chúng đi chung một đường.
             */
            $table->foreignId('guide_handover_id')->nullable()
                ->constrained('guide_handovers')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('tour_schedule_id');
            $table->index('requested_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_handover_requests');
    }
};
