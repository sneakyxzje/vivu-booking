<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đánh dấu tour dùng để THỬ NGHIỆM NGHIỆP VỤ.
 *
 * ## Vì sao cần một cờ riêng thay vì tự nhớ tour nào là tour thử
 *
 * Sân thử nghiệm cho phép một việc mà cả hệ thống còn lại cấm: **tua ngày khởi hành của một chuyến
 * đã có khách**. Đó đúng là đường mà `AdminTourController` vừa khóa lại, vì dời ngày là dời hạn trả
 * nốt của từng đơn trên chuyến — và làm thế trên dữ liệu thật thì khách mất cọc vì một cái hạn
 * không ai kịp làm gì.
 *
 * Nhưng để chứng minh nghiệp vụ thì không còn cách nào khác. Lệnh nhắc và lệnh hủy chỉ đụng tới đơn
 * ĐÃ tới mốc, mà mốc thì tính theo ngày khởi hành. Không tua được ngày thì người xem phải chờ đúng
 * số ngày ấy trôi qua mới thấy hệ thống phản ứng — tức là không bao giờ chứng minh được trong một
 * buổi ngồi trước máy.
 *
 * Nên quyền nguy hiểm ấy phải có một hàng rào rõ ràng, ghi trong cơ sở dữ liệu, đọc được bằng mắt
 * ở màn quản trị. Cờ nằm trên TOUR chứ không nằm trên chuyến: một tour thử thì mọi chuyến của nó
 * đều là dữ liệu thử, và người vận hành nhìn danh sách tour là biết ngay cái nào đụng vào được.
 *
 * Mặc định `false`. Mọi tour đang có, và mọi tour tạo qua biểu mẫu, đều là tour thật.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->boolean('is_sandbox')->default(false)->after('is_featured');

            // Danh sách tour lọc theo cờ này ở màn quản trị, và trang khách thì loại chúng ra.
            $table->index('is_sandbox');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropIndex(['is_sandbox']);
            $table->dropColumn('is_sandbox');
        });
    }
};
