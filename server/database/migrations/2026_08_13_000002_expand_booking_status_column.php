<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D03 - Nới cột bookings.status để nhận thêm 'completed' và 'no_show'.
 *
 * Cột đang là enum ba giá trị 'pending', 'confirmed', 'cancelled'. Enum BookingStatus đã khai
 * đủ tám trạng thái từ trước nhưng ghi xuống cơ sở dữ liệu thì hỏng, nên đơn của chuyến đã đi
 * xong vẫn nằm nguyên ở 'confirmed'. Hệ quả là không phân biệt được khách đã đi với khách bỏ
 * chuyến, mà đó chính là số liệu để tính ghế chết và tỷ lệ vắng.
 *
 * Đổi hẳn sang varchar(20) thay vì nới enum thêm hai giá trị: ba trạng thái còn lại của vòng
 * đời ('deposit_paid', 'paid', 'transferred') sẽ vào ở N01 và I01, mỗi lần thêm một giá trị lại
 * phải khóa bảng đổi enum là không cần thiết. Cột tour_schedules.status cũng đã đi đường này ở
 * migration 2026_08_11_000003, giữ hai bảng cùng một cách làm thì đỡ phải nhớ ngoại lệ.
 *
 * Việc chặn giá trị lạ chuyển lên tầng ứng dụng, nơi enum BookingStatus giữ danh sách hợp lệ.
 *
 * Thêm luôn completed_at. Biết đơn kết thúc lúc nào là điều kiện để ghi nhận doanh thu theo
 * đúng kỳ ở task S02; suy ngược từ updated_at thì sai ngay lần đầu có ai đó sửa ghi chú đơn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('status');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");

            return;
        }

        // Laravel 13 đổi kiểu cột không cần doctrine/dbal và tự dựng lại bảng trên SQLite.
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
        });
    }

    public function down(): void
    {
        // Thu hẹp lại thì hai giá trị mới không còn chỗ đứng, phải quy đổi trước.
        // Quy đổi này mất thông tin: 'no_show' gộp chung vào 'confirmed' nên sau khi rollback
        // không phân biệt được khách đã đi với khách bỏ chuyến nữa. Chấp nhận được vì rollback
        // chỉ dùng lúc phát triển; trên máy chạy thật thì đi tiếp chứ không lùi.
        DB::table('bookings')
            ->whereIn('status', ['completed', 'no_show'])
            ->update(['status' => 'confirmed']);

        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE bookings MODIFY status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending'"
            );

            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending')->change();
        });
    }
};
