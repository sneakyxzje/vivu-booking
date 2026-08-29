<?php

namespace App\Enums;

/**
 * Liên hệ khách bằng đường nào.
 *
 * Ghi lại vì khi có tranh cãi "công ty có báo tôi đâu", câu trả lời khác nhau tùy đường: gọi điện
 * thì chỉ có lời khai của điều hành, còn email hay Zalo thì còn bản lưu để mở ra đối chiếu.
 */
enum ContactChannel: string
{
    case Phone = 'phone';
    case Zalo = 'zalo';
    case Email = 'email';
    case InPerson = 'in_person';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Gọi điện',
            self::Zalo => 'Nhắn Zalo',
            self::Email => 'Gửi email',
            self::InPerson => 'Gặp trực tiếp',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
