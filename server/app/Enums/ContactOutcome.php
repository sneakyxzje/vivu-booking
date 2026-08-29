<?php

namespace App\Enums;

/**
 * Kết quả của cuộc liên hệ.
 *
 * Ba khả năng, và cả ba đều phải ghi được — kể cả hai khả năng xấu. Chỉ cho ghi lúc khách đồng ý
 * thì nhật ký thành bộ sưu tập tin vui: không ai tra ngược được rằng công ty đã gọi bốn lần mà
 * không ai bắt máy, mà đó chính là thứ cần đến khi có tranh cãi.
 */
enum ContactOutcome: string
{
    /** Khách đồng ý phương án điều hành đưa ra. */
    case Agreed = 'agreed';

    /** Khách nghe máy nhưng không đồng ý. */
    case Refused = 'refused';

    /** Không liên lạc được: không bắt máy, sai số, không trả lời. */
    case Unreachable = 'unreachable';

    public function label(): string
    {
        return match ($this) {
            self::Agreed => 'Khách đồng ý',
            self::Refused => 'Khách không đồng ý',
            self::Unreachable => 'Không liên lạc được',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
