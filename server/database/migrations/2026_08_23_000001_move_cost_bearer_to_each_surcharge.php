<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ai chịu chi phí là thuộc tính của TỪNG KHOẢN, không phải của cả sự cố.
 *
 * `CostBearer` đã nói điều này từ đầu trong khối chú thích của nó, nhưng lược đồ lại đặt
 * `who_bears` lên `schedule_incidents` — một giá trị cho cả sự cố. Nên tình huống thật nhất của
 * nhóm O lại là tình huống không ghi được:
 *
 *   Bão, tàu không chạy. Thuê xe đường bộ thay tàu — hãng chịu, vì đó là nghĩa vụ tổ chức. Kẹt
 *   lại một đêm, phòng và hai bữa ăn — khách chịu, vì đó là tiêu dùng cá nhân. Buổi tham quan đảo
 *   đã bán mà không đi được — hoàn cho khách.
 *
 * Một sự cố, ba khoản, ba người chịu khác nhau.
 *
 * Cột trên `schedule_incidents` GIỮ NGUYÊN, đổi vai thành gợi ý mặc định: điều hành mở phương án
 * ra thì các dòng khoản chi được điền sẵn theo nó, rồi sửa từng dòng. Xóa cột đi thì mất luôn dữ
 * liệu của các sự cố đã xử lý, mà chúng là bằng chứng khi có khiếu nại.
 *
 * Cột `booking_surcharge_id` trên sổ giao dịch trả lời câu "khoản phụ thu này đã thu chưa, thu
 * lúc nào" mà không phải dò chuỗi trong phần ghi chú.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_surcharges', function (Blueprint $table) {
            $table->string('who_bears', 20)->nullable()->after('kind');
        });

        /*
         * Chép giá trị cũ xuống từng khoản. Dùng truy vấn con chứ không UPDATE ... JOIN: SQLite
         * không hiểu cú pháp join trong UPDATE, mà máy này chạy SQLite còn máy nhà chạy MySQL.
         */
        DB::statement(<<<'SQL'
            UPDATE booking_surcharges
            SET who_bears = (
                SELECT who_bears FROM schedule_incidents
                WHERE schedule_incidents.id = booking_surcharges.schedule_incident_id
            )
            WHERE schedule_incident_id IS NOT NULL
        SQL);

        Schema::table('booking_payments', function (Blueprint $table) {
            $table->foreignId('booking_surcharge_id')
                ->nullable()
                ->after('booking_id')
                ->constrained('booking_surcharges')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_surcharge_id');
        });

        Schema::table('booking_surcharges', function (Blueprint $table) {
            $table->dropColumn('who_bears');
        });
    }
};
