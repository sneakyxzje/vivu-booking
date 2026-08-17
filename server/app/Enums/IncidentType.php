<?php

namespace App\Enums;

/** Loại sự cố dọc đường. Xem docs/nghiep-vu/04-luong-dieu-hanh.md mục 6.1. */
enum IncidentType: string
{
    case Weather = 'weather';
    case Vehicle = 'vehicle';
    case Health = 'health';
    case Supplier = 'supplier';
    case Security = 'security';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Weather => 'Thời tiết',
            self::Vehicle => 'Phương tiện',
            self::Health => 'Sức khỏe khách',
            self::Supplier => 'Nhà cung cấp',
            self::Security => 'An ninh, an toàn',
            self::Other => 'Khác',
        };
    }

    /**
     * Loại này mặc định do hãng chịu chi phí hay không.
     *
     * Chỉ là gợi ý ban đầu cho điều hành, không phải quyết định của hệ thống: cùng một cơn bão
     * có khoản hãng chịu (xe thay thế) và khoản khách chịu (đêm phòng ở thêm). Người quyết vẫn
     * là điều hành, xem bảng phân bổ ở tài liệu 04 mục 6.3.
     */
    public function thuongDoHangChiu(): bool
    {
        return in_array($this, [self::Vehicle, self::Supplier], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
