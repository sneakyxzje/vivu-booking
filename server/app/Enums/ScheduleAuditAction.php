<?php

namespace App\Enums;

/**
 * Các thao tác được ghi vào nhật ký chuyến khởi hành.
 *
 * Tách khỏi BookingAuditAction vì đây là thay đổi của cả chuyến, không thuộc về đơn nào.
 */
enum ScheduleAuditAction: string
{
    /** Dời hạn chốt danh sách khách gửi nhà cung cấp. */
    case DeadlineChanged = 'deadline_changed';

    /** Hủy cả chuyến, kèm phương án cho từng đơn đã thanh toán. */
    case Cancelled = 'cancelled';

    /** Đổi hướng dẫn viên giữa chừng, kèm biên bản bàn giao. */
    case GuideHandover = 'guide_handover';

    public function label(): string
    {
        return match ($this) {
            self::DeadlineChanged => 'Đổi hạn chốt danh sách',
            self::Cancelled => 'Hủy chuyến',
            self::GuideHandover => 'Bàn giao hướng dẫn viên',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
