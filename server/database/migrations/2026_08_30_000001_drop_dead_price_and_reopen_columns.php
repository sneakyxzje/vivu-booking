<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bỏ những cột không còn ai ghi vào nữa.
 *
 * **`tours.price` và `tours.discount_price`.** Từ khi có giá theo loại khách (2026_08_02), mọi
 * đường tạo và sửa tour đều ghi `price = adult_price` và `discount_price = null` cứng trong mã.
 * Hai cột ấy không còn là dữ liệu, chúng là một bản sao luôn bằng nhau — nhưng giao diện vẫn đọc
 * chúng theo kiểu `adult_price ?? discount_price ?? price`, tức vẫn còn ba nguồn cho một con số.
 * Ai đọc cơ sở dữ liệu sau này phải tự đoán cột nào mới là giá thật.
 *
 * **`bookings.reopen_reason`, `reopened_at`, `reopened_by`.** Tuyến mở lại đơn đã hủy đã được gỡ
 * có chủ đích (xem chú thích ở `AdminBookingController`, chỗ hàm `reopen()` từng nằm): hủy là
 * trạng thái kết thúc. Ba cột ở lại làm người đọc tưởng chức năng ấy vẫn còn.
 *
 * `BookingAuditAction::Reopened` thì GIỮ, không gỡ — nhật ký cũ có thể còn dòng mang giá trị đó,
 * và bỏ nhánh enum đi thì mở trang lịch sử đơn ấy là lỗi.
 *
 * Bảng `booking_checkins` cũng KHÔNG đụng tới. Nó là bản gốc để đối chiếu khi có khiếu nại, lý do
 * ghi ở migration 2026_08_12_150000.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['price', 'discount_price']);
        });

        /*
         * `reopened_by` là khóa ngoại, phải gỡ ràng buộc trước rồi mới bỏ cột được.
         *
         * Gộp cả ba vào một `dropColumn` thì SQLite báo "unknown column reopened_by in foreign key
         * definition": nó dựng lại bảng theo lược đồ mới nhưng ràng buộc cũ vẫn trỏ vào cột vừa
         * biến mất. Tách làm hai bước, cột có ràng buộc đi trước.
         */
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_by');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['reopen_reason', 'reopened_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->default(0)->after('description');
            $table->decimal('discount_price', 12, 2)->nullable()->after('price');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->text('reopen_reason')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedBigInteger('reopened_by')->nullable();
        });
    }
};
