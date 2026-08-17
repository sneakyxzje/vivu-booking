<?php

namespace App\Enums;

/** Khoản tiền sinh ra từ sự cố đi theo chiều nào. */
enum SurchargeKind: string
{
    /** Khách trả thêm: đêm phòng ở thêm, bữa ăn thêm, chương trình thay thế đắt hơn. */
    case Surcharge = 'surcharge';

    /** Hoàn cho khách: phần chương trình đã trả tiền nhưng không dùng được. */
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Surcharge => 'Khách trả thêm',
            self::Refund => 'Hoàn cho khách',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
