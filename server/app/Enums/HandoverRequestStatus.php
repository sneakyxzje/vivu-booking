<?php

namespace App\Enums;

/**
 * Vòng đời một phiếu xin bàn giao. **Hai trạng thái, không hơn.**
 *
 * Trước đây có bốn: chờ duyệt, đã duyệt, đã từ chối, đã rút lại — kèm một luồng phê duyệt và ba
 * thao tác riêng. Đó là bộ máy dựng quanh một việc rất nhỏ: hướng dẫn viên nói "tôi cần được
 * thay", điều hành đọc rồi quyết.
 *
 * "Từ chối" và "rút lại" gộp vào `Closed`, kèm ghi chú. Sự khác nhau giữa "điều hành không đồng
 * ý" và "hướng dẫn viên đỡ rồi" nằm ở câu ghi chú, không cần thành hai trạng thái mà mọi màn hình
 * phải biết phân biệt.
 */
enum HandoverRequestStatus: string
{
    /** Đang chờ điều hành xử lý. Người gửi vẫn giữ nguyên quyền phụ trách. */
    case Pending = 'pending';

    /** Đã xử lý xong — hoặc đã đổi người, hoặc điều hành đóng lại kèm lý do. */
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ điều hành xử lý',
            self::Closed => 'Đã xử lý',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Closed;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
