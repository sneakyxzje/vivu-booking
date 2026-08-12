<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B01 - Chính sách hủy theo mốc thời gian.
 *
 * Vì sao tách thành bảng thay vì viết cứng bảng phí trong mã: mỗi tour có thể cần chính sách
 * riêng. Tour lễ tết và tour nước ngoài có phí hủy cao hơn vì vé máy bay và phòng khách sạn
 * đã xuất trước.
 *
 * Đơn hàng giữ khóa ngoại riêng chứ không đọc qua tour, để sửa chính sách về sau không hồi tố
 * lên đơn đã ký. Cùng nguyên tắc với việc đơn lưu giá tại thời điểm đặt.
 *
 * Tài liệu: docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 2.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('is_default');
        });

        Schema::create('cancellation_policy_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cancellation_policy_id')
                ->constrained('cancellation_policies')
                ->cascadeOnDelete();

            // Khoảng thời gian còn lại tới lúc khởi hành, tính bằng giờ.
            // max_hours_before để trống nghĩa là không có giới hạn trên, tức bậc xa nhất.
            $table->unsignedInteger('min_hours_before');
            $table->unsignedInteger('max_hours_before')->nullable();
            $table->unsignedTinyInteger('refund_percent');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['cancellation_policy_id', 'min_hours_before'], 'idx_policy_rules_lookup');
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->foreignId('cancellation_policy_id')->nullable()->after('status')
                ->constrained('cancellation_policies')->nullOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Sao chép từ tour lúc tạo đơn. Không đọc qua tour khi cần tính phí, vì tour có thể
            // đã đổi sang chính sách khác sau khi khách đặt.
            $table->foreignId('cancellation_policy_id')->nullable()->after('cancellation_plan')
                ->constrained('cancellation_policies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancellation_policy_id');
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancellation_policy_id');
        });

        Schema::dropIfExists('cancellation_policy_rules');
        Schema::dropIfExists('cancellation_policies');
    }
};
