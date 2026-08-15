<?php

namespace App\Console\Commands;

use App\Enums\BookingAuditAction;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\TourSchedule;
use App\Services\BookingAuditLogger;
use App\Services\BookingHoldService;
use App\Services\ScheduleLifecycleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * X12 - Dọn đơn giữ chỗ còn treo của chuyến đã kết thúc.
 *
 * Đơn chờ thanh toán bình thường tự hủy khi qua expires_at. Nhóm này là phần lọt lưới: tác vụ
 * nền chết một hôm, hoặc đơn được tạo bằng tay mà không đặt hạn. Chuyến đã đi xong rồi mà đơn
 * vẫn ghi "chờ thanh toán", và nó vẫn đang chiếm chỗ trong booked_people - tức lệnh đối chiếu
 * số chỗ sẽ báo lệch mà không ai hiểu vì sao.
 *
 * Xem docs/nghiep-vu/08-danh-muc-edge-case.md, tình huống J06.
 */
#[Signature('bookings:expire-stale-holds')]
#[Description('Dọn các đơn chờ thanh toán còn treo của chuyến đã kết thúc')]
class ExpireStaleHolds extends Command
{
    private const REASON = 'Đơn giữ chỗ chưa thanh toán của chuyến đã kết thúc, hệ thống tự dọn.';

    public function __construct(
        private readonly BookingHoldService $holdService,
        private readonly ScheduleLifecycleService $lifecycle,
        private readonly BookingAuditLogger $auditLogger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $daDon = 0;

        $this->candidates()->chunkById(100, function ($bookings) use (&$daDon) {
            foreach ($bookings as $booking) {
                if (!$this->chuyenDaKetThuc($booking->schedule)) {
                    continue;
                }

                if (!$this->holdService->expireStaleHold($booking, self::REASON)) {
                    continue;
                }

                $this->auditLogger->logStatusChange(
                    $booking->refresh(),
                    BookingAuditAction::Cancelled,
                    'pending',
                    'cancelled',
                    self::REASON,
                    ['seats_released' => (bool) $booking->seats_released],
                );

                $daDon++;
                $this->line("Đã dọn đơn #{$booking->id} của chuyến #{$booking->tour_schedule_id}.");
            }
        });

        $this->info("Đã dọn {$daDon} đơn giữ chỗ tồn đọng.");

        return self::SUCCESS;
    }

    /**
     * Đơn chờ thanh toán của chuyến đã qua ngày kết thúc.
     *
     * Lọc rộng theo cột status của chuyến rồi để effectiveStatus quyết định, vì trạng thái lưu
     * trong cơ sở dữ liệu có thể chậm hơn đồng hồ khi tác vụ nền bị dừng - mà đó lại chính là
     * hoàn cảnh sinh ra nhóm đơn treo này.
     */
    private function candidates()
    {
        return Booking::query()
            ->where('status', 'pending')
            ->whereNotNull('tour_schedule_id')
            ->whereHas('schedule', function ($query) {
                $query->whereNotIn('status', [ScheduleStatus::Cancelled->value]);
            })
            ->with('schedule.tour:id,number_of_days')
            ->orderBy('id');
    }

    private function chuyenDaKetThuc(?TourSchedule $schedule): bool
    {
        return $schedule
            && $this->lifecycle->effectiveStatus($schedule) === ScheduleStatus::Completed;
    }
}
