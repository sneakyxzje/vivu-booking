<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chuẩn hóa cột tour_schedules.status trên mọi driver.
 *
 * Migration 000001 chỉ nới cột trên MySQL, phần còn lại thoát sớm bằng
 * `if (DB::getDriverName() !== 'mysql') return;`. Hệ quả là trên SQLite cột vẫn giữ
 * khai báo gốc `varchar not null default 'active'`, trong khi MySQL đã thành
 * `varchar(20) not null default 'open'`.
 *
 * Chênh lệch này nguy hiểm ở chỗ nó im lặng: bản ghi tạo mà không truyền status sẽ nhận
 * 'active' trên máy phát triển và 'open' trên máy chạy thật. 'active' không còn là giá trị
 * hợp lệ của vòng đời nên mọi chỗ đọc đều phải rơi vào nhánh quy đổi dự phòng.
 *
 * Migration này chạy trên mọi driver và idempotent, nên cơ sở dữ liệu đã chạy 000001
 * lẫn cơ sở dữ liệu mới đều hội tụ về cùng một trạng thái.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Quét nốt các giá trị cũ còn sót, phòng trường hợp có bản ghi được tạo sau 000002
        // bởi mã cũ hoặc seeder chưa cập nhật.
        DB::statement("
            UPDATE tour_schedules
            SET status = CASE
                WHEN status = 'active' THEN 'open'
                WHEN status = 'full' THEN 'closed'
                WHEN status = 'inactive' THEN 'cancelled'
                ELSE status
            END
            WHERE status IN ('active', 'full', 'inactive')
        ");

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tour_schedules MODIFY status VARCHAR(20) NOT NULL DEFAULT 'open'");

            return;
        }

        // Laravel 13 đổi kiểu cột không cần doctrine/dbal và tự dựng lại bảng trên SQLite.
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->string('status', 20)->default('open')->change();
        });
    }

    public function down(): void
    {
        // Không đảo ngược. Kiểu cột và giá trị mặc định được migration 000001 khôi phục
        // về enum cũ khi rollback, làm thêm ở đây chỉ gây map hai lần.
    }
};
