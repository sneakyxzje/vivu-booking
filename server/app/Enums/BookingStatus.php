<?php

namespace App\Enums;

/**
 * Vòng đời của một đơn đặt tour.
 *
 * Định nghĩa đầy đủ ở docs/nghiep-vu/01-tac-nhan-va-vong-doi.md mục 5.
 *
 * Lưu ý quan trọng khi dùng: cột bookings.status hiện là enum chỉ nhận ba giá trị
 * 'pending', 'confirmed', 'cancelled' (xem migration 2026_06_28_000000_create_bookings_table).
 * Các trạng thái còn lại đã có trong enum này vì chúng là đích đến đã chốt trong tài liệu,
 * nhưng chưa ghi xuống cơ sở dữ liệu được cho tới khi các migration tương ứng vào:
 *
 *   Completed, NoShow  -> task D03
 *   DepositPaid, Paid  -> task N01
 *   Transferred        -> task I01
 *
 * Dùng liveValues() khi cần biết cột hiện chấp nhận những giá trị nào.
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

    /** Đã đi xong. Chưa dùng, chờ task D03. */
    case Completed = 'completed';

    /** Đã thanh toán nhưng không có mặt lúc khởi hành. Chưa dùng, chờ task D03. */
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

    /**
     * Đơn đang chiếm chỗ trên chuyến.
     * NoShow vẫn tính là chiếm chỗ vì khách đã trả tiền và chỗ đó đã mất.
     */
    public function holdsSeat(): bool
    {
        return in_array($this, [
            self::Pending,
            self::DepositPaid,
            self::Paid,
            self::Confirmed,
            self::NoShow,
        ], true);
    }

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
     * Ba giá trị mà cột bookings.status hiện chấp nhận.
     * Xóa hàm này khi các migration D03, N01, I01 đã mở rộng cột.
     *
     * @return array<int, string>
     */
    public static function liveValues(): array
    {
        return [self::Pending->value, self::Confirmed->value, self::Cancelled->value];
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
