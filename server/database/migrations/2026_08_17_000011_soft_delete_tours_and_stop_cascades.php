<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Xóa mềm tour, và gỡ cascade khỏi những bảng mang chứng từ.
 *
 * ## Vấn đề
 *
 * `bookings.tour_id`, `reviews.tour_id` và `group_booking_requests.tour_id` đều khai
 * `onDelete('cascade')`. Nghĩa là **một lệnh xóa tour ở bất kỳ đâu** - trong mã, trong tinker,
 * hay gõ tay vào cơ sở dữ liệu - đều kéo theo toàn bộ đơn hàng của tour đó, và theo dây chuyền
 * là hành khách, sổ giao dịch, nhật ký thay đổi, nhật ký cổng thanh toán.
 *
 * Lớp `TourDeletionService` có chặn, nhưng đó là hàng rào ở tầng ứng dụng: nó chỉ giữ được lối
 * đi qua nó. Chứng từ tài chính không nên phụ thuộc vào việc mọi người sau này đều nhớ gọi đúng
 * lớp dịch vụ.
 *
 * ## Hai thay đổi
 *
 * **1. Tour xóa mềm.** Xóa tour nay chỉ đặt `deleted_at`: tour biến mất khỏi mọi danh sách nhưng
 * hàng dữ liệu còn nguyên, và đơn cũ vẫn tra ngược ra tên tour (các quan hệ trỏ tới `Tour` đã
 * thêm `withTrashed`). Xóa nhầm thì khôi phục được.
 *
 * **2. Ba khóa ngoại đổi từ cascade sang restrict.** Kể cả khi ai đó cố xóa cứng, cơ sở dữ liệu
 * sẽ từ chối thay vì xóa sạch. Đây là lớp phòng thủ thứ hai, đặt ở chỗ không ai lách qua được.
 *
 * Giữ nguyên cascade ở `tour_images`, `tour_itineraries`, `category_tour`, `tour_service` và
 * `tour_schedules`: đó là **thành phần cấu tạo nên tour**, không phải chứng từ giao dịch với
 * khách. Xóa tour thì chúng đi theo là đúng.
 *
 * ## Giới hạn trên SQLite
 *
 * SQLite không đổi được khóa ngoại bằng ALTER TABLE, phải dựng lại cả bảng. Phần đổi khóa ngoại
 * vì thế chỉ chạy trên MySQL - tức máy chạy thật. Máy phát triển dùng SQLite vẫn giữ cascade cũ,
 * nhưng hàng rào ở `TourDeletionService` chạy trên cả hai nên hành vi của ứng dụng không khác
 * nhau; bộ kiểm thử chứng minh điều đó.
 */
return new class extends Migration
{
    /**
     * Bảng nào mang chứng từ và phải chặn xóa cứng.
     *
     * @var array<int, string>
     */
    private const BANG_CHUNG_TU = ['bookings', 'reviews', 'group_booking_requests'];

    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->softDeletes();
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::BANG_CHUNG_TU as $bang) {
            if (! Schema::hasTable($bang)) {
                continue;
            }

            Schema::table($bang, function (Blueprint $table) use ($bang) {
                // Bỏ khóa cũ rồi dựng lại với hành vi restrict. Cột và chỉ mục giữ nguyên nên
                // không vướng lỗi 1553 như lần đổi chỉ mục của bảng phân công hướng dẫn viên.
                $table->dropForeign($bang . '_tour_id_foreign');
                $table->foreign('tour_id')->references('id')->on('tours')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (self::BANG_CHUNG_TU as $bang) {
                if (! Schema::hasTable($bang)) {
                    continue;
                }

                Schema::table($bang, function (Blueprint $table) use ($bang) {
                    $table->dropForeign($bang . '_tour_id_foreign');
                    $table->foreign('tour_id')->references('id')->on('tours')->cascadeOnDelete();
                });
            }
        }

        Schema::table('tours', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
