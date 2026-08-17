<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * "Bây giờ" theo đồng hồ treo tường Việt Nam.
 *
 * Ứng dụng chạy múi giờ UTC (config/app.php), nhưng các cột ngày giờ nghiệp vụ lưu **giờ Việt Nam
 * dưới dạng mộc**: điều hành gõ 07:00 nghĩa là 07:00 giờ Việt Nam, và cột lưu đúng chuỗi đó. Xem
 * chú thích ở TourSchedule::serializeDate.
 *
 * Vì thế so một giá trị người dùng nhập với `now()` là so hai thứ khác hệ quy chiếu, lệch đúng 7
 * tiếng. Lỗi này không lộ ra trong kiểm thử vì kiểm thử dựng dữ liệu bằng chính `now()`, tức cả
 * hai vế cùng là UTC; nó chỉ lộ ra khi có người thật gõ giờ từ trình duyệt.
 *
 * Hàm này trả về mốc hiện tại đã quy về cùng hệ quy chiếu với các cột ấy, để phép so sánh đúng.
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
