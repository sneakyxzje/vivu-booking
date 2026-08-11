<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\TourSchedule;

/**
 * Các quy tắc chặn thao tác trên đơn đặt tour.
 *
 * Vì sao phải nằm ở tầng dịch vụ chứ không phải trong controller: cùng một quy tắc được
 * áp cho nhiều lối vào khác nhau (khách tự hủy, quản trị hủy, sau này là chuyển chuyến).
 * Viết trong controller thì chỉ cần quên một chỗ là thủng, và không có cách nào biết đã quên.
 *
 * Xem docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 4 và 5.
 */
class BookingPolicyService
{
    public function __construct(private ScheduleLifecycleService $lifecycle)
    {
    }

    /**
     * Chặn hủy đơn khi chuyến đã khởi hành, hoặc khi đơn đã ở trạng thái cuối.
     *
     * Truyền $schedule khi lời gọi đang nằm trong giao dịch đã khóa dòng chuyến, để kiểm tra
     * trên đúng bản ghi vừa khóa thay vì một bản đọc cũ.
     *
     * Lưu ý phạm vi: đây là quy tắc cho thao tác hủy do người dùng khởi xướng. Tác vụ nền nhả
     * chỗ của đơn quá hạn giữ chỗ không đi qua đây, vì đơn đó chưa thanh toán và chưa vào danh
     * sách đoàn, chặn lại chỉ để tồn rác. Xem BookingHoldService.
     */
    public function assertCancellable(Booking $booking, ?TourSchedule $schedule = null): void
    {
        $this->assertScheduleAllowsCancellation($schedule ?? $booking->schedule);

        $status = BookingStatus::tryFrom((string) $booking->status);

        if ($status?->isTerminal()) {
            // Giữ mã 400 thay vì 422 để không đổi hợp đồng API của luồng hủy đang chạy.
            throw new BusinessRuleException(
                sprintf('Đơn đặt tour đang ở trạng thái "%s" nên không thể hủy.', $status->label()),
                400,
            );
        }
    }

    /**
     * Chuyến đã khởi hành thì không còn đường hủy nào, kể cả của quản trị viên.
     * Đây không phải chuyện phân quyền mà là chuyện chi phí đã phát sinh và nhà cung cấp
     * đã phục vụ. Thay vào đó dùng nghiệp vụ ghi nhận vắng mặt hoặc rời đoàn giữa chừng.
     */
    public function assertScheduleAllowsCancellation(?TourSchedule $schedule): void
    {
        if (!$schedule) {
            return;
        }

        $status = $this->lifecycle->currentStatus($schedule);

        if (!$status->blocksCancellation()) {
            return;
        }

        throw new BusinessRuleException(match ($status) {
            ScheduleStatus::InProgress => 'Chuyến đi đã khởi hành nên không thể hủy đơn. '
                . 'Vui lòng liên hệ điều hành để ghi nhận khách vắng mặt hoặc rời đoàn giữa chừng.',
            default => 'Chuyến đi đã kết thúc nên không thể hủy đơn. '
                . 'Vui lòng liên hệ điều hành nếu cần khiếu nại hoặc yêu cầu hoàn tiền.',
        });
    }
}
