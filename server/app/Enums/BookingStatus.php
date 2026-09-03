<?php

namespace App\Enums;

/**
 * Vòng đời của một đơn đặt tour.
 *
 * Định nghĩa đầy đủ ở docs/nghiep-vu/01-tac-nhan-va-vong-doi.md mục 5.
 *
 * Lưu ý quan trọng khi dùng: cột bookings.status là varchar nhưng luồng nghiệp vụ mới chỉ ghi
 * xuống năm giá trị. Ba trạng thái còn lại đã có trong enum này vì chúng là đích đến đã chốt
 * trong tài liệu, nhưng chưa có luồng nào sinh ra cho tới khi các task tương ứng vào:
 *
 *   DepositPaid, Paid  -> task N01
 *   Transferred        -> task I01
 *
 * Dùng liveValues() khi cần biết luồng hiện tại có thể sinh ra những giá trị nào.
 */
enum BookingStatus: string
{
    /** Đã tạo, chưa thanh toán, đang giữ chỗ tạm tới expires_at. */
    case Pending = 'pending';

    /** Đã đóng cọc, chỗ được giữ chắc chắn. Chưa dùng, chờ task N01. */
    case DepositPaid = 'deposit_paid';

    /** Đã thanh toán đủ. Chưa dùng, chờ task N01. */
    case Paid = 'paid';

    /** Đã vào danh sách đoàn. */
    case Confirmed = 'confirmed';

    /** Đã đi xong. */
    case Completed = 'completed';

    /** Đã thanh toán nhưng không có mặt lúc khởi hành. */
    case NoShow = 'no_show';

    /** Đã hủy. */
    case Cancelled = 'cancelled';

    /** Đã chuyển sang chuyến hoặc tour khác. Chưa dùng, chờ task I01. */
    case Transferred = 'transferred';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ thanh toán',
            self::DepositPaid => 'Đã đóng cọc',
            self::Paid => 'Đã thanh toán',
            self::Confirmed => 'Đã xác nhận',
            self::Completed => 'Đã hoàn thành',
            self::NoShow => 'Khách không có mặt',
            self::Cancelled => 'Đã hủy',
            self::Transferred => 'Đã chuyển chuyến',
        };
    }

    /**
     * Trạng thái cuối: đơn đã kết thúc vòng đời, không hủy được nữa.
     * Hủy một đơn đã hủy, đã đi xong hoặc đã chuyển đi đều vô nghĩa.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::NoShow,
            self::Cancelled,
            self::Transferred,
        ], true);
    }

    /*
     * ĐÃ GỠ: `holdsSeat()`.
     *
     * Câu hỏi "đơn này có đang chiếm chỗ không" trên thực tế được trả lời bằng SQL ở
     * `CheckSeatConsistency::occupiedSeats()` — nó phải hỏi cho cả một bảng chứ không cho từng bản
     * ghi, nên không dùng được hàm này. Không dòng nào gọi tới nó.
     *
     * Xem lý do đầy đủ ở chú thích cùng loại trong `PassengerCheckinStatus`: một hàm mang tên như
     * một luật mà không ai gọi thì tệ hơn là không có, vì nó làm người đọc tin rằng luật đang chạy.
     */

    /**
     * Đơn đã trả tiền, tính vào số khách để quyết định chốt chuyến.
     *
     * Không tính Pending: đó là giữ chỗ chưa thanh toán, có thể tự hủy khi hết hạn.
     * Chốt chuyến dựa trên giữ chỗ chưa trả tiền là chốt trên số ảo.
     * Xem docs/nghiep-vu/04-luong-dieu-hanh.md mục 1.2.
     */
    public function isPaid(): bool
    {
        return in_array($this, [
            self::DepositPaid,
            self::Paid,
            self::Confirmed,
        ], true);
    }

    /**
     * Đơn đã vào danh sách đoàn gửi cho hướng dẫn viên.
     *
     * Gồm cả hai trạng thái sau chuyến. Chuyến đi xong thì đơn chuyển sang 'completed' hoặc
     * 'no_show'; lọc đúng 'confirmed' thì danh sách đoàn của mọi chuyến đã kết thúc bỗng trống
     * rỗng, mất luôn dữ liệu điểm danh dùng để đối chiếu khi khách khiếu nại về sau.
     *
     * Không gồm 'pending': đó là giữ chỗ chưa thanh toán, chưa có tên trong danh sách đoàn.
     */
    public function isInManifest(): bool
    {
        return in_array($this, [
            self::Confirmed,
            self::Completed,
            self::NoShow,
        ], true);
    }

    /** @return array<int, string> */
    public static function manifestValues(): array
    {
        return array_values(array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => $case->isInManifest()),
        ));
    }

    /**
     * Đơn có tiền thật đã vào, tính vào doanh thu.
     *
     * NoShow vẫn tính: khách đã trả tiền, không có mặt thì không được hoàn, suất vẫn phải trả
     * cho nhà cung cấp. Bỏ nhóm này ra khỏi doanh thu thì mỗi chuyến chạy xong doanh thu lại
     * tụt xuống một cách khó hiểu.
     *
     * Đây là con số cho bảng điều khiển, không phải ghi nhận doanh thu kế toán. Nguyên tắc ghi
     * nhận theo thời điểm chuyến kết thúc nằm ở task S02, docs/nghiep-vu/10-tai-chinh-va-ke-toan.md.
     */
    public function countsAsRevenue(): bool
    {
        return in_array($this, [
            self::DepositPaid,
            self::Paid,
            self::Confirmed,
            self::Completed,
            self::NoShow,
        ], true);
    }

    /** @return array<int, string> */
    public static function revenueValues(): array
    {
        return array_values(array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => $case->countsAsRevenue()),
        ));
    }

    /** @return array<int, string> */
    public static function paidValues(): array
    {
        return array_values(array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => $case->isPaid()),
        ));
    }

    /** @return array<int, string> */
    public static function terminalValues(): array
    {
        return array_values(array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => $case->isTerminal()),
        ));
    }

    /**
     * Năm giá trị mà luồng nghiệp vụ hiện tại có thể sinh ra.
     * Xóa hàm này khi N01 và I01 đã mở nốt ba trạng thái còn lại.
     *
     * @return array<int, string>
     */
    public static function liveValues(): array
    {
        return [
            self::Pending->value,
            self::Confirmed->value,
            self::Cancelled->value,
            self::Completed->value,
            self::NoShow->value,
        ];
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
