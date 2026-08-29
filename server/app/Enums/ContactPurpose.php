<?php

namespace App\Enums;

/**
 * Cuộc liên hệ này bàn về việc gì.
 *
 * Không phải để phân loại cho đẹp báo cáo. Nó là **phạm vi của sự đồng ý**: khách gật đầu chuyện
 * dời lịch không có nghĩa là gật đầu chuyện hủy đơn. Chuyển chuyến chỉ nhận bản ghi mang mục đích
 * `Transfer`, nên một cuộc gọi về việc khác không vô tình trở thành giấy phép chuyển chuyến.
 */
enum ContactPurpose: string
{
    /** Bàn việc dời khách sang chuyến khác. Đây là mục đích duy nhất hiện có ràng buộc. */
    case Transfer = 'transfer';

    /** Bàn việc hủy đơn và mức hoàn. Ghi lại được, nhưng chưa chặn luồng hủy. */
    case Cancellation = 'cancellation';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Bàn chuyển chuyến',
            self::Cancellation => 'Bàn hủy đơn',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
