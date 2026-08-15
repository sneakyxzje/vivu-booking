<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * I01 - Chuyển chuyến và chuyển tour.
 *
 * Câu hỏi hội đồng nêu đầu tiên. Ba tình huống theo docs/nghiep-vu/02-luong-dat-tour.md mục 4.1:
 * khách đổi ngày trong cùng tour, khách đổi sang tour khác, và hãng chuyển vì chuyến gốc bị hủy
 * hoặc bị ghép.
 *
 * Lưu thành bảng riêng chứ không chỉ đổi tour_schedule_id trên đơn: chuyển chuyến là sự kiện có
 * tiền đi kèm và có thể lặp lại, nên cần biết đơn đã chuyển mấy lần, từ đâu sang đâu, chênh bao
 * nhiêu, ai duyệt. Ghi đè cột trên đơn thì lần chuyển trước biến mất.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignId('from_schedule_id')->nullable()
                ->constrained('tour_schedules')->nullOnDelete();
            $table->foreignId('to_schedule_id')->nullable()
                ->constrained('tour_schedules')->nullOnDelete();

            // Giữ cả tour hai đầu: chuyển sang tour khác thì hai chuyến thuộc hai tour, và báo
            // cáo về sau cần biết mà không phải lần ngược qua chuyến đã có thể bị xóa.
            $table->foreignId('from_tour_id')->nullable()
                ->constrained('tours')->nullOnDelete();
            $table->foreignId('to_tour_id')->nullable()
                ->constrained('tours')->nullOnDelete();

            // 'customer' hoặc 'company'. Hãng khởi xướng thì miễn phí và bỏ qua hạn báo trước,
            // vì lỗi không thuộc về khách.
            $table->string('initiated_by', 20)->default('customer');

            // Dương khi chuyến đích đắt hơn, âm khi rẻ hơn.
            $table->decimal('price_difference', 12, 2)->default(0);

            // Phí đổi lịch từ lần chuyển thứ hai trở đi.
            $table->decimal('fee', 12, 2)->default(0);

            $table->text('reason')->nullable();

            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'created_at']);
            $table->index(['to_schedule_id', 'created_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Đếm số lần đã chuyển để áp quy tắc miễn phí lần đầu.
            $table->unsignedTinyInteger('transfer_count')->default(0)->after('completed_at');

            // Dành cho luồng tách đơn khi chỉ chuyển một phần số khách (task I04, chưa làm).
            $table->foreignId('split_from_booking_id')->nullable()->after('transfer_count')
                ->constrained('bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('split_from_booking_id');
            $table->dropColumn('transfer_count');
        });

        Schema::dropIfExists('booking_transfers');
    }
};
