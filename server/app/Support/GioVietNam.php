<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * "Bây giờ" theo đồng hồ treo tường Việt Nam.
 *
 * Các cột ngày giờ nghiệp vụ lưu **giờ Việt Nam dưới dạng mộc**: điều hành gõ 07:00 nghĩa là 07:00
 * giờ Việt Nam, và cột lưu đúng chuỗi đó. Xem chú thích ở TourSchedule::serializeDate.
 *
 * ## Vì sao lớp này vẫn còn dù `APP_TIMEZONE` đã là Asia/Ho_Chi_Minh
 *
 * Chú thích cũ ở đây nói "ứng dụng chạy múi giờ UTC" — **không còn đúng**: cả `config/app.php` lẫn
 * `.env.example` đều đặt `Asia/Ho_Chi_Minh`, nên `now()` đã trả về đúng đồng hồ treo tường Việt
 * Nam và không còn lệch 7 tiếng nào để sửa.
 *
 * Giữ lớp này vì nó là chỗ **ghi ra thành lời** rằng các cột kia là giờ mộc, và vì nó khóa cách
 * đọc "bây giờ" vào một múi giờ tường minh thay vì phụ thuộc một biến môi trường. Đổi
 * `APP_TIMEZONE` sang UTC — chuyện một người triển khai hoàn toàn có thể làm vì tưởng đó là chuẩn
 * — thì mọi phép so qua đây vẫn đúng, còn `now()` trần ở nơi khác thì không.
 */
class GioVietNam
{
    public const MUI_GIO = 'Asia/Ho_Chi_Minh';

    /**
     * Mốc hiện tại dưới dạng mộc, cùng hệ quy chiếu với các cột ngày giờ trong cơ sở dữ liệu.
     *
     * Cố ý đi qua format rồi parse lại: lấy phần giờ treo tường của Việt Nam rồi bỏ nhãn múi giờ
     * đi, đúng như cách các cột kia đang lưu.
     */
    public static function bayGio(): Carbon
    {
        return Carbon::parse(now(self::MUI_GIO)->format('Y-m-d H:i:s'));
    }
}
