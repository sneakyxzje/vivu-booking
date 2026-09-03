<?php

namespace App\Enums;

/**
 * Loại yêu cầu thay đổi mà khách gửi lên.
 *
 * Một bảng dùng chung cho bốn loại thay vì bốn bảng riêng: cả bốn đều cùng vòng đời gửi rồi
 * chờ duyệt, cùng người duyệt, cùng chỗ hiển thị. Phần khác nhau nằm hết trong cột payload.
 *
 * Xem docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 5.2.
 */
enum ChangeRequestType: string
{
    /** Xin hủy đơn đã thanh toán. */
    case Cancel = 'cancel';

    /** Xin chuyển sang chuyến khác. Chưa dùng, chờ nhóm I. */
    case Transfer = 'transfer';

    /** Xin đổi số khách. Chưa dùng, chờ nhóm J. */
    case ChangeGuests = 'change_guests';

    /** Xin đổi thông tin hành khách. Chưa dùng, chờ nhóm G. */
    case ChangePassenger = 'change_passenger';

    public function label(): string
    {
        return match ($this) {
            self::Cancel => 'Yêu cầu hủy đơn',
            self::Transfer => 'Yêu cầu chuyển chuyến',
            self::ChangeGuests => 'Yêu cầu đổi số khách',
            self::ChangePassenger => 'Yêu cầu đổi thông tin hành khách',
        };
    }

    /*
     * ĐÃ GỠ: `isImplemented()`.
     *
     * Nó canh việc tạo yêu cầu thuộc loại chưa có luồng xử lý. Nhưng chỉ có đúng một đường tạo
     * yêu cầu — `BookingChangeRequestService::requestCancellation()` — và đường đó ghi cứng
     * `ChangeRequestType::Cancel`, nên không có gì để canh. Không dòng nào gọi tới nó.
     *
     * Ba loại còn lại vẫn khai ở trên vì bảng dùng chung; khi nhóm nào cài đặt chúng thì dựng lại
     * phép canh cùng lúc với đường ghi mới, chứ không để nó nằm sẵn mà không ai gọi.
     */

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
