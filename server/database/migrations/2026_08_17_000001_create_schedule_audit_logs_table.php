<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhật ký thay đổi ở mức chuyến khởi hành.
 *
 * booking_audit_logs gắn cứng vào booking_id nên không ghi được những thay đổi thuộc về cả
 * chuyến. Hạn chốt danh sách là loại đó: nó là một cái mốc của cả chuyến, và dịch cái mốc ấy
 * đổi cùng lúc quyền bán chỗ, quyền sửa tên hành khách, quyền chuyển chuyến, quyền ghép chuyến,
 * và việc chỗ có quay về kho khi khách hủy hay không.
 *
 * Câu hỏi bảng này phải trả lời được: ba tháng sau khách khiếu nại "hạn chốt là 19/08 sao tôi
 * hủy ngày 18 vẫn mất chỗ", thì lúc khách hủy hạn chốt đang là ngày nào, và ai đã đổi nó.
 *
 * Xem docs/nghiep-vu/16-sua-han-chot.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tour_schedule_id')
                ->constrained('tour_schedules')
                ->cascadeOnDelete();

            // Để trống khi tác vụ nền tự làm.
            $table->foreignId('actor_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // Chép vai trò tại thời điểm thao tác, vì tài khoản có thể đổi vai trò về sau.
            $table->string('actor_role', 20)->nullable();

            $table->string('action', 40);

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->text('reason')->nullable();

            // Dài 45 để chứa cả IPv6.
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index(['tour_schedule_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_audit_logs');
    }
};
