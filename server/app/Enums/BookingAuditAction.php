<?php

namespace App\Enums;

/**
 * Các loại thao tác được ghi vào nhật ký đơn hàng.
 *
 * Enum thay vì chuỗi tự do để màn lịch sử dịch được sang tiếng Việt ở một chỗ, và để thêm loại
 * mới thì mọi nơi hiển thị báo lỗi biên dịch ngay chứ không âm thầm hiện mã kỹ thuật cho người
 * dùng đọc.
 */
enum BookingAuditAction: string
{
    case Created = 'created';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Reopened = 'reopened';

    /** Điều hành trả chỗ của ghế chết về kho để bán lại. */
    case SeatsReleased = 'seats_released';

    case CancelRequested = 'cancel_requested';
    case CancelRequestApproved = 'cancel_request_approved';
    case CancelRequestRejected = 'cancel_request_rejected';
    case CancelRequestWithdrawn = 'cancel_request_withdrawn';

    /** Chốt đơn sau khi chuyến kết thúc, thành đã hoàn thành hoặc khách không có mặt. */
    case Finalized = 'finalized';

    case PassengersUpdated = 'passengers_updated';

    /** Sửa thông tin người đặt: tên, điện thoại, thư điện tử liên hệ. */
    case ContactUpdated = 'contact_updated';

    /** Chuyển đơn sang chuyến khác, cùng tour hoặc khác tour. */
    case Transferred = 'transferred';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Tạo đơn',
            self::Confirmed => 'Xác nhận đơn',
            self::Cancelled => 'Hủy đơn',
            self::Reopened => 'Mở lại đơn đã hủy',
            self::SeatsReleased => 'Trả chỗ về kho',
            self::CancelRequested => 'Khách gửi yêu cầu hủy',
            self::CancelRequestApproved => 'Duyệt yêu cầu hủy',
            self::CancelRequestRejected => 'Từ chối yêu cầu hủy',
            self::CancelRequestWithdrawn => 'Khách rút lại yêu cầu hủy',
            self::Finalized => 'Chốt đơn sau chuyến',
            self::PassengersUpdated => 'Cập nhật danh sách hành khách',
            self::ContactUpdated => 'Sửa thông tin liên hệ',
            self::Transferred => 'Chuyển sang chuyến khác',
        };
    }

    /**
     * Thao tác này có làm thay đổi tiền hay không.
     *
     * Dùng để lọc nhanh khi đối soát: câu hỏi thường gặp không phải "đơn này có gì thay đổi"
     * mà là "những lần nào tiền của đơn này bị đụng tới".
     */
    public function touchesMoney(): bool
    {
        return in_array($this, [
            self::Cancelled,
            self::CancelRequestApproved,
            self::Reopened,
            // Chuyển chuyến làm đổi tổng tiền của đơn: giá tour đích khác, và từ lần thứ hai
            // còn cộng thêm phí đổi lịch.
            self::Transferred,
        ], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
