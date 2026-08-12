<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C01 - Cột phục vụ việc hủy đơn và quyết định trả chỗ.
 *
 * Cột quan trọng nhất là seats_released. Nếu chỉ bỏ qua việc trừ booked_people mà không ghi
 * lại, số chỗ đã bán sẽ phồng lên vĩnh viễn và không ai truy ra được chỗ nào là ghế chết để
 * mở bán lại.
 *
 * Tài liệu: docs/nghiep-vu/07-thiet-ke-du-lieu.md mục 1.3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Phân loại lý do hủy. Báo cáo doanh thu và trách nhiệm pháp lý phụ thuộc vào đây:
            // khách tự đổi ý khác hẳn với hãng hủy chuyến.
            $table->string('cancel_type', 20)->nullable()->after('cancel_reason');
            $table->dateTime('cancelled_at')->nullable()->after('cancel_type');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')
                ->constrained('users')->nullOnDelete();

            // Chỗ của đơn này đã được trả về kho để bán lại chưa.
            // false nghĩa là ghế chết: chỗ trống về mặt vật lý nhưng không bán được vì phòng
            // và suất ăn đã chốt theo danh sách gửi nhà cung cấp.
            $table->boolean('seats_released')->default(true)->after('cancelled_by');
            $table->dateTime('seats_released_at')->nullable()->after('seats_released');
            $table->foreignId('seats_released_by')->nullable()->after('seats_released_at')
                ->constrained('users')->nullOnDelete();

            // Số tiền đã tính để hoàn, theo chính sách hủy tại thời điểm hủy.
            $table->decimal('refund_amount', 12, 2)->nullable()->after('seats_released_by');

            // Phương án xử lý khi hãng hủy cả chuyến: refund, transfer, credit.
            $table->string('cancellation_plan', 20)->nullable()->after('refund_amount');
        });

        // Đơn đã hủy từ trước: chỗ đã được trả rồi, đừng làm sai số liệu đang có.
        DB::table('bookings')
            ->where('status', 'cancelled')
            ->update(['seats_released' => true]);

        Schema::table('bookings', function (Blueprint $table) {
            // Phục vụ màn hình liệt kê ghế chết ở task C03.
            $table->index(['status', 'seats_released'], 'idx_bookings_status_seats_released');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_status_seats_released');

            $table->dropConstrainedForeignId('seats_released_by');
            $table->dropConstrainedForeignId('cancelled_by');

            $table->dropColumn([
                'cancel_type',
                'cancelled_at',
                'seats_released',
                'seats_released_at',
                'refund_amount',
                'cancellation_plan',
            ]);
        });
    }
};
