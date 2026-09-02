<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chép đơn giá của từng loại khách vào đơn, tại thời điểm đặt.
 *
 * Đơn đã chép chính sách hủy vào chính nó (`cancellation_policy_id`) để sửa bảng phí về sau không
 * hồi tố lên đơn đã bán. Đơn giá thì chưa, và bản in hợp đồng đọc thẳng `tours.adult_price` tại
 * thời điểm IN.
 *
 * Hậu quả xuất hiện ngay lần đầu điều hành sửa giá tour: mọi hợp đồng in ra sau đó có bảng đơn giá
 * mới nhân với số khách cũ, trong khi dòng tổng cộng vẫn là `total_amount` chốt lúc đặt. Hai con số
 * trong cùng một văn bản không cộng ra nhau — và đó là văn bản hai bên ký.
 *
 * Đơn cũ lấy giá tour hiện tại làm giá trị khởi tạo. Không chính xác bằng giá thật lúc họ đặt,
 * nhưng đó là thông tin duy nhất còn lại, và nó vẫn tốt hơn việc tiếp tục đọc một con số trôi theo
 * thời gian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('adult_price', 12, 2)->nullable()->after('infant_count');
            $table->decimal('child_price', 12, 2)->nullable()->after('adult_price');
            $table->decimal('infant_price', 12, 2)->nullable()->after('child_price');
        });

        // Cập nhật theo từng tour: cách này chạy giống nhau trên cả SQLite lẫn MySQL, khác với
        // UPDATE ... JOIN vốn phải viết hai kiểu cú pháp.
        DB::table('tours')->select('id', 'adult_price', 'child_price', 'infant_price')
            ->orderBy('id')
            ->chunk(200, function ($tours) {
                foreach ($tours as $tour) {
                    DB::table('bookings')
                        ->where('tour_id', $tour->id)
                        ->update([
                            'adult_price' => $tour->adult_price,
                            'child_price' => $tour->child_price,
                            'infant_price' => $tour->infant_price,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['adult_price', 'child_price', 'infant_price']);
        });
    }
};
