<?php

namespace App\Enums;

/**
 * Ai chịu chi phí phát sinh từ sự cố.
 *
 * Nguyên tắc chia, theo docs/nghiep-vu/04-luong-dieu-hanh.md mục 6.3: **hãng chịu chi phí thuộc
 * nghĩa vụ tổ chức, khách chịu chi phí thuộc tiêu dùng cá nhân thực tế phát sinh.**
 *
 * Xe hỏng phải thuê xe khác là nghĩa vụ tổ chức, hãng chịu. Kẹt lại một đêm phải ở thêm phòng và
 * ăn thêm bữa là tiêu dùng cá nhân, khách chịu. Cùng một cơn bão sinh ra cả hai loại, nên đây là
 * thuộc tính của **từng khoản chi**, không phải của cả sự cố.
 */
enum CostBearer: string
{
    case Company = 'company';
    case Customer = 'customer';
    case Insurance = 'insurance';
    case Shared = 'shared';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Hãng chịu',
            self::Customer => 'Khách chịu',
            self::Insurance => 'Bảo hiểm chi trả',
            self::Shared => 'Chia đôi',
        };
    }

    /** Có sinh khoản khách phải trả thêm hay không. */
    public function khachPhaiTra(): bool
    {
        return in_array($this, [self::Customer, self::Shared], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
