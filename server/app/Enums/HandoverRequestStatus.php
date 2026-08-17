<?php

namespace App\Enums;

/**
 * Vòng đời một yêu cầu bàn giao do hướng dẫn viên gửi.
 *
 * Chờ duyệt nghe như chậm, nhưng thực ra an toàn hơn tự bàn giao ngay: người cũ **vẫn giữ quyền
 * phụ trách cho tới lúc duyệt**, nên không có khoảnh khắc nào đoàn không có ai chịu trách nhiệm
 * trên hệ thống.
 */
enum HandoverRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /** Hướng dẫn viên tự rút lại, ví dụ đỡ sốt rồi vẫn dẫn tiếp được. */
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ điều hành duyệt',
            self::Approved => 'Đã duyệt và bàn giao',
            self::Rejected => 'Đã từ chối',
            self::Withdrawn => 'Đã rút lại',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
