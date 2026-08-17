<?php

namespace App\Enums;

/** Mức nghiêm trọng của sự cố, do hướng dẫn viên đánh giá tại hiện trường. */
enum IncidentSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Nhẹ',
            self::Medium => 'Vừa',
            self::High => 'Nghiêm trọng',
        };
    }

    /**
     * Mức cần điều hành xử lý ngay.
     *
     * Dùng để đẩy lên đầu danh sách chờ xử lý. Hệ thống không tự quyết gì thay điều hành, chỉ
     * sắp thứ tự để việc gấp không nằm lẫn dưới việc thường.
     */
    public function canXuLyNgay(): bool
    {
        return $this === self::High;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
