<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hai mốc giữa của một chuyến: tới điểm đến, và rời điểm đến để về.
 *
 * ## Vì sao cần
 *
 * Chuyến đã có hai mốc ngoài cùng — `start_date` là lúc rời điểm khởi hành, `end_date` là lúc về
 * tới nơi. Thiếu hai mốc giữa thì trang chi tiết chỉ nói được "đi ngày nào, về ngày nào", trong
 * khi thứ khách cần để đặt phòng đêm đầu hoặc hẹn người nhà đón là giờ tới nơi.
 *
 * ## Vì sao là dateTime chứ không phải cột giờ
 *
 * Xe giường nằm rời Hà Nội 22h và tới Sa Pa 5h sáng **hôm sau**. Lưu mỗi phần giờ thì không có
 * chỗ nào ghi được "hôm sau", và mọi phép tính độ dài chặng sẽ ra số âm. Cùng lý do khiến
 * `end_date` là dateTime chứ không phải một cột giờ gắn vào ngày cuối.
 *
 * ## Cả hai đều nullable, và sẽ còn nullable lâu
 *
 * Đây là **giờ áng chừng do điều hành điền**, không suy ra được từ đâu: nó phụ thuộc quãng đường,
 * loại xe, số lần nghỉ dọc đường. Chuyến cũ không có, và không có gì để điền hộ — bịa một con số
 * rồi in lên trang cho khách đọc thì tệ hơn hẳn việc để trống. Giao diện giấu dòng nào rỗng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->dateTime('arrival_at')->nullable()->after('end_date');
            $table->dateTime('return_departure_at')->nullable()->after('arrival_at');
        });
    }

    public function down(): void
    {
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->dropColumn(['arrival_at', 'return_departure_at']);
        });
    }
};
