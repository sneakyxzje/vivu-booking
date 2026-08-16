<?php

namespace App\Enums;

/**
 * Hai mô hình kinh doanh tour.
 *
 * Xem docs/nghiep-vu/04-luong-dieu-hanh.md mục 2.2.
 */
enum TourType: string
{
    /** Nhiều khách lẻ chung một đoàn. Có mức khách tối thiểu, giá thấp hơn. */
    case Shared = 'shared';

    /** Một đoàn đặt trọn chuyến. Không có mức tối thiểu, giá tính theo đoàn. */
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Shared => 'Tour ghép',
            self::Private => 'Tour riêng',
        };
    }

    /**
     * Loại này có ghép chuyến được không.
     *
     * Dồn hai đoàn riêng vào một chuyến là phá vỡ chính thứ khách trả tiền để có: chuyến của
     * riêng họ. Ghép chỉ có nghĩa với tour ghép.
     */
    public function canMergeSchedules(): bool
    {
        return $this === self::Shared;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
