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

    public function label(): string
    {
        return match ($this) {
            self::DeadlineChanged => 'Đổi hạn chốt danh sách',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
