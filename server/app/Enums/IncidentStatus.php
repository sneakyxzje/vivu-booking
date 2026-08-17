<?php

namespace App\Enums;

/**
 * Vòng đời một báo cáo sự cố.
 *
 * Ba trạng thái này chính là ba vai trò khác nhau: hướng dẫn viên báo, điều hành quyết, rồi đóng
 * lại khi mọi khoản tiền đã xử lý xong.
 */
enum IncidentStatus: string
{
    /** Hướng dẫn viên vừa báo, điều hành chưa xem. */
    case Reported = 'reported';

    /** Điều hành đã quyết phương án và phân bổ chi phí. */
    case Reviewed = 'reviewed';

    /** Mọi khoản phụ thu và hoàn đã xử lý xong. */
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Chờ điều hành xử lý',
            self::Reviewed => 'Đã có phương án',
            self::Resolved => 'Đã đóng',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
