<?php

namespace App\Enums;

/**
 * Vòng đời một yêu cầu booking đoàn.
 *
 *   pending_quote ──→ quoted ──→ confirmed (sinh Booking thật, chiếm chỗ)
 *        │               │
 *        ├──→ rejected ←─┤   (điều hành từ chối, kèm lý do)
 *        └──→ withdrawn ←┘   (khách rút yêu cầu)
 *
 * `quoted → quoted` hợp lệ: thương lượng có qua có lại, báo giá lại là chuyện thường. Ba trạng
 * thái cuối là điểm dừng - đã chốt thì mọi thay đổi đi qua đơn hàng, không quay lại yêu cầu.
 *
 * Báo giá quá hạn KHÔNG phải một trạng thái: nó suy ra từ `quote_expires_at` đã qua. Đặt thành
 * trạng thái riêng thì phải có tác vụ nền canh giờ để chuyển, thêm một chỗ hỏng mà không thêm
 * thông tin gì - vì "quá hạn" chỉ có nghĩa lúc đọc.
 */
enum GroupRequestStatus: string
{
    case PendingQuote = 'pending_quote';
    case Quoted = 'quoted';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::PendingQuote => 'Chờ báo giá',
            self::Quoted => 'Đã báo giá',
            self::Confirmed => 'Đã chốt',
            self::Rejected => 'Đã từ chối',
            self::Withdrawn => 'Khách đã rút',
        };
    }

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingQuote => [self::Quoted, self::Rejected, self::Withdrawn],
            self::Quoted => [self::Quoted, self::Confirmed, self::Rejected, self::Withdrawn],
            self::Confirmed, self::Rejected, self::Withdrawn => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
