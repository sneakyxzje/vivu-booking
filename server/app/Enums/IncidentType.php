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

    /*
     * ĐÃ GỠ: `thuongDoHangChiu()`.
     *
     * Nó gợi ý mặc định người chịu chi phí theo loại sự cố. Màn xử lý sự cố không đọc nó: điều
     * hành chọn người chịu cho từng khoản, và `IncidentService` lùi về `who_bears` của phương án
     * khi dòng để trống. Không dòng nào gọi tới nó.
     *
     * Nguyên tắc phân bổ vẫn ở docs/nghiep-vu/04-luong-dieu-hanh.md mục 6.3. Muốn gợi ý sẵn cho
     * người bấm thì thêm nó vào phần `options` của màn sự cố cùng lúc với việc dựng lại hàm này.
     */

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
