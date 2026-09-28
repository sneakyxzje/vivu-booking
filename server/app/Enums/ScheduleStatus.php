<?php

namespace App\Enums;

/**
 * Vòng đời của một lịch khởi hành.
 *
 * Định nghĩa đầy đủ ở docs/nghiep-vu/01-tac-nhan-va-vong-doi.md mục 4.
 * Enum này là nguồn sự thật duy nhất cho tên trạng thái và các đường chuyển hợp lệ:
 * migration, model, service và lệnh chạy nền đều phải lấy từ đây, không viết chuỗi rời rạc.
 */
enum ScheduleStatus: string
{
    /** Chưa chốt chạy; chỉ nhận đặt khi còn chỗ và chưa tới hạn. */
    case Open = 'open';

    /** Đã chốt chắc chắn khởi hành, đủ số khách tối thiểu. */
    case Confirmed = 'confirmed';

    /** Đoàn đang đi. */
    case InProgress = 'in_progress';

    /** Đã kết thúc, kể cả khi bị rút ngắn do sự cố. */
    case Completed = 'completed';

    /** Hủy chuyến. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Chờ chốt',
            self::Confirmed => 'Đã chốt chuyến',
            self::InProgress => 'Đang khởi hành',
            self::Completed => 'Đã kết thúc',
            self::Cancelled => 'Đã hủy chuyến',
        };
    }

    /**
     * Các trạng thái đi tiếp hợp lệ.
     *
     * - Không có đường InProgress sang Cancelled. Chuyến đã khởi hành thì chi phí đã phát sinh
     *   và nhà cung cấp đã phục vụ, không thể coi như chưa từng xảy ra. Muốn dừng giữa chừng
     *   thì vẫn kết thúc bằng Completed kèm bản ghi sự cố.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Điều kiện vòng đời; model kiểm tra thêm hạn đặt và chỗ trống. */
    public function isBookable(): bool
    {
        return $this === self::Open;
    }

    public function isRunning(): bool
    {
        return $this === self::InProgress;
    }

    /** Trạng thái cuối, không đi tiếp được nữa. */
    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Chuyến ở trạng thái này thì mọi đường hủy đơn đều bị chặn.
     * Dùng cho BookingPolicyService, xem docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 4.
     */
    public function blocksCancellation(): bool
    {
        return in_array($this, [self::InProgress, self::Completed], true);
    }

    /**
     * Quy đổi ba giá trị của enum cũ ('active', 'inactive', 'full') sang vòng đời mới.
     *
     * Cần tham số $hasDeparted vì giá trị cũ mất thông tin: migration 2026_07_05 đã gộp cả
     * 'canceled' lẫn 'completed' vào 'inactive'. Dựa vào ngày khởi hành để tách lại hai
     * trường hợp đó, nếu không mọi chuyến đã đi xong sẽ bị đánh nhầm thành hủy chuyến.
     */
    public static function fromLegacy(string $legacy, bool $hasDeparted = false): self
    {
        // Chuyến đã qua ngày khởi hành thì coi như đã kết thúc, bất kể giá trị cũ là gì.
        // Không phân biệt được chuyến bị hủy trong quá khứ, nhưng dữ liệu hiện tại sinh từ
        // seeder và chỉ có 'active', nên trường hợp đó không tồn tại trên thực tế.
        if ($hasDeparted) {
            return self::Completed;
        }

        return match ($legacy) {
            'active' => self::Open,
            'full', 'closed' => self::Open,
            'inactive' => self::Cancelled,
            default => self::tryFrom($legacy) ?? self::Open,
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
