<?php

namespace App\Enums;

/**
 * Trạng thái kiểm duyệt của một đánh giá.
 *
 * Ba giá trị, không hơn. "Đã ẩn tạm" hay "chờ khách sửa lại" nghe hợp lý nhưng không tương ứng với
 * thao tác nào có thật của điều hành: họ chỉ quyết đúng một việc, đăng hay không đăng.
 */
enum ReviewStatus: string
{
    /** Vừa gửi, chưa ai xem. Chỉ chính người viết nhìn thấy. */
    case Pending = 'pending';

    /** Đã duyệt. Hiện công khai và được tính vào điểm trung bình của tour. */
    case Approved = 'approved';

    /** Bị từ chối. Không hiện công khai, không tính điểm, nhưng KHÔNG xóa — người viết cần
     *  đọc được lý do, và điều hành cần đối chiếu nếu khách khiếu nại. */
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ duyệt',
            self::Approved => 'Đã duyệt',
            self::Rejected => 'Đã từ chối',
        };
    }
}
