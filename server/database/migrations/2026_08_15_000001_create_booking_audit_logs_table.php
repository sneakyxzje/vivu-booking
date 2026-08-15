<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E01 - Nhật ký thay đổi đơn hàng.
 *
 * Hệ thống giờ có nhiều thao tác chạm tiền và chỗ: hủy đơn sinh ghế chết, duyệt khoản hoàn, mở
 * lại chỗ đã chết, mở lại đơn hủy nhầm, chốt đơn thành khách không có mặt. Dấu vết của từng
 * thao tác nằm rải rác ở cancelled_by, reviewed_by, seats_released_by, mỗi bảng một kiểu, và
 * không chỗ nào kể lại được câu chuyện của một đơn theo thứ tự thời gian.
 *
 * Bảng này là chỗ đó. Câu hỏi cần trả lời được: ai đã duyệt khoản hoàn này, lúc nào, lý do gì,
 * và trước đó đơn đang ở trạng thái nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();

            // Để trống khi tác vụ nền tự làm, ví dụ nhả chỗ quá hạn hay chốt đơn sau chuyến.
            $table->foreignId('actor_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // Chép lại vai trò tại thời điểm thao tác. Một tài khoản có thể đổi vai trò về sau,
            // nhưng nhật ký phải giữ đúng vai trò lúc người đó bấm nút.
            $table->string('actor_role', 20)->nullable();

            $table->string('action', 40);

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->text('reason')->nullable();

            // Dài 45 để chứa cả IPv6.
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            // Màn lịch sử luôn lọc theo đơn rồi sắp theo thời gian.
            $table->index(['booking_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_audit_logs');
    }
};
