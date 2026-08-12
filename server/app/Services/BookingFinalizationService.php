<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PassengerCheckinStatus;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\ItineraryCheckpoint;
use App\Models\PassengerCheckin;
use App\Models\TourSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * D03 - Chốt đơn khi chuyến đã kết thúc.
 *
 * Vì sao cần: chuyến chạy xong thì chuyển sang 'completed', còn đơn thì nằm nguyên ở 'confirmed'
 * vĩnh viễn. Không có gì phân biệt khách đã đi với khách bỏ chuyến, mà đó chính là số liệu để
 * tính tỷ lệ vắng và để biết suất nào đã mất trắng.
 *
 * Xem docs/nghiep-vu/01-tac-nhan-va-vong-doi.md mục 5.
 */
class BookingFinalizationService
{
    public function __construct(
        private ScheduleLifecycleService $lifecycle,
    ) {
    }

    /**
     * Chốt toàn bộ đơn còn treo của một chuyến đã kết thúc.
     *
     * @return array{completed: int, no_show: int, skipped: int}
     */
    public function finalizeSchedule(TourSchedule $schedule, ?Carbon $now = null): array
    {
        $now ??= now();

        $ketQua = ['completed' => 0, 'no_show' => 0, 'skipped' => 0];

        if ($this->lifecycle->effectiveStatus($schedule, $now) !== ScheduleStatus::Completed) {
            return $ketQua;
        }

        $diemDauTien = $this->firstCheckpoint($schedule);

        $bookings = Booking::query()
            ->where('tour_schedule_id', $schedule->getKey())
            ->whereIn('status', BookingStatus::paidValues())
            ->orderBy('id')
            ->get(['id']);

        foreach ($bookings as $booking) {
            $chot = $this->finalizeBooking($booking->id, $diemDauTien, $now);

            match ($chot) {
                BookingStatus::Completed => $ketQua['completed']++,
                BookingStatus::NoShow => $ketQua['no_show']++,
                default => $ketQua['skipped']++,
            };
        }

        return $ketQua;
    }

    /**
     * Chốt một đơn. Trả về null khi đơn đã rời khỏi nhóm cần chốt trong lúc chờ khóa.
     *
     * Khóa dòng rồi mới đọc lại trạng thái: lệnh này chạy mỗi phút và quản trị viên có thể đang
     * hủy chính đơn đó. Nếu kiểm tra trên bản đọc trước khi khóa thì một đơn vừa bị hủy vẫn có
     * thể bị ghi đè thành 'completed', xóa mất việc hủy.
     */
    public function finalizeBooking(
        int $bookingId,
        ?ItineraryCheckpoint $firstCheckpoint,
        ?Carbon $now = null,
    ): ?BookingStatus {
        $now ??= now();

        return DB::transaction(function () use ($bookingId, $firstCheckpoint, $now) {
            $locked = Booking::query()
                ->whereKey($bookingId)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                return null;
            }

            if (!in_array((string) $locked->status, BookingStatus::paidValues(), true)) {
                return null;
            }

            $trangThai = $this->resolveStatus($locked, $firstCheckpoint);

            $locked->forceFill([
                'status' => $trangThai->value,
                'completed_at' => $now,
            ])->save();

            return $trangThai;
        });
    }

    /**
     * Đơn này kết thúc ở đã hoàn thành hay khách không có mặt.
     *
     * Quy tắc: chỉ kết luận không có mặt khi có bằng chứng đầy đủ, tức mọi hành khách trên đơn
     * đều đã được điểm danh tại điểm đón đầu tiên và tất cả đều 'absent'. Thiếu bằng chứng thì
     * coi là đã đi.
     *
     * Vì sao khắt khe một chiều như vậy: 'no_show' là kết luận bất lợi cho khách, nó đóng luôn
     * đường hoàn tiền. Ghi nhầm một đơn đã đi thành không có mặt gây tranh cãi với khách thật,
     * còn ghi nhầm chiều ngược lại chỉ làm báo cáo tỷ lệ vắng thấp hơn thực tế. Khi hướng dẫn
     * viên bỏ điểm danh thì lỗi thuộc về vận hành, không nên quy sang khách.
     *
     * 'excused' không tính là vắng: đó là vắng đã thống nhất trước với hướng dẫn viên, khác hẳn
     * với khách không tới và không liên lạc được.
     */
    public function resolveStatus(Booking $booking, ?ItineraryCheckpoint $firstCheckpoint): BookingStatus
    {
        if (!$firstCheckpoint) {
            return BookingStatus::Completed;
        }

        $hanhKhachIds = $booking->passengers()->pluck('id');

        if ($hanhKhachIds->isEmpty()) {
            return BookingStatus::Completed;
        }

        $trangThaiDiemDanh = PassengerCheckin::query()
            ->where('itinerary_checkpoint_id', $firstCheckpoint->getKey())
            ->whereIn('booking_passenger_id', $hanhKhachIds)
            ->pluck('status');

        // Còn người chưa điểm danh thì chưa đủ bằng chứng để kết luận cả đơn không có mặt.
        if ($trangThaiDiemDanh->count() < $hanhKhachIds->count()) {
            return BookingStatus::Completed;
        }

        $tatCaVang = $trangThaiDiemDanh->every(
            fn ($status) => $status === PassengerCheckinStatus::Absent
        );

        return $tatCaVang ? BookingStatus::NoShow : BookingStatus::Completed;
    }

    /**
     * Điểm đón đầu tiên của hành trình: ngày nhỏ nhất, rồi thứ tự nhỏ nhất trong ngày đó.
     *
     * Đây là mốc quyết định khách có lên đoàn hay không. Các điểm dừng sau chỉ nói khách có
     * tham gia hoạt động nào hay không, vắng ở đó là chuyện khác với không có mặt lúc khởi hành.
     */
    public function firstCheckpoint(TourSchedule $schedule): ?ItineraryCheckpoint
    {
        return ItineraryCheckpoint::query()
            ->whereHas('tourItinerary', function ($query) use ($schedule) {
                $query->where('tour_id', $schedule->tour_id);
            })
            ->with('tourItinerary:id,day_number')
            ->get()
            ->sortBy([
                ['tourItinerary.day_number', 'asc'],
                ['sequence', 'asc'],
            ])
            ->first();
    }
}
