<?php

namespace App\Enums;

/**
 * Vòng đời của một yêu cầu thay đổi.
 *
 * Xem docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 5.2.
 */
enum ChangeRequestStatus: string
{
    /** Đã gửi, đang chờ điều hành xem. */
    case Pending = 'pending';

    /** Điều hành đã duyệt và yêu cầu đã được thực thi. */
    case Approved = 'approved';

    /** Điều hành từ chối, bắt buộc kèm lý do. */
    case Rejected = 'rejected';

    /** Khách tự rút lại yêu cầu trước khi có ai duyệt. */
    case CancelledByCustomer = 'cancelled_by_customer';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ duyệt',
            self::Approved => 'Đã duyệt',
            self::Rejected => 'Bị từ chối',
            self::CancelledByCustomer => 'Khách đã rút lại',
        };
    }

    /** Đã đóng thì không xử lý được nữa, dù duyệt hay từ chối hay khách rút. */
    public function isClosed(): bool
    {
        return $this !== self::Pending;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
