<?php

namespace App\Enums;

/**
 * Vòng đời một khoản phụ thu hoặc hoàn.
 *
 * Điểm quan trọng: **chờ duyệt thì chưa có hiệu lực với khách.** Hướng dẫn viên báo cáo sự cố
 * nhưng không tạo được khoản tiền nào; điều hành mới là người quyết, và trước khi họ duyệt thì
 * con số chưa được nói với khách như một khoản phải trả.
 */
enum SurchargeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';

    /** Điều hành quyết định không thu, dù ban đầu xác định khách chịu. */
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ duyệt',
            self::Approved => 'Đã duyệt, chờ thu',
            self::Paid => 'Đã thu',
            self::Waived => 'Đã miễn',
        };
    }

    /** Khoản này đã có hiệu lực với khách chưa. */
    public function coHieuLuc(): bool
    {
        return in_array($this, [self::Approved, self::Paid], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
