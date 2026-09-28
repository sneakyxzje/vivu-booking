<?php

namespace App\Enums;

/**
 * Vòng đời của một đề xuất thay đổi (Admin gửi cho Khách hàng).
 */
enum ProposalStatus: string
{
    /** Đang chờ khách hàng phản hồi. */
    case Pending = 'pending';

    /** Khách hàng đã chấp nhận một trong các phương án. */
    case Accepted = 'accepted';

    /** Khách hàng từ chối tất cả các phương án. */
    case Rejected = 'rejected';

    /** Khách hàng không phản hồi trong thời gian quy định (quá hạn). */
    case Expired = 'expired';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
