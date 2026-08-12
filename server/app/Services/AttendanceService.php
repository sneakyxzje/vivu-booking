<?php

namespace App\Services;

use App\Enums\PassengerCheckinStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BookingPassenger;
use App\Models\CheckpointPhoto;
use App\Models\ItineraryCheckpoint;
use App\Models\PassengerCheckin;
use App\Models\PassengerCheckinHistory;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * H08 - Các quy tắc kiểm tra khi điểm danh.
 *
 * Chín quy tắc ở docs/nghiep-vu/04-luong-dieu-hanh.md mục 5.3, gom về một chỗ vì cùng một
 * thao tác điểm danh có thể tới từ nhiều màn hình khác nhau, và điểm danh là dữ liệu dùng để
 * đối chiếu khi có khiếu nại nên không được phép ghi bừa.
 */
class AttendanceService
{
    /** Ghi bù sau khoảng này thì đánh dấu là ghi muộn. */
    private const LATE_ENTRY_AFTER_HOURS = 24;

    /** Ghi chú giải thích phải đủ dài để có ý nghĩa khi đọc lại. */
    private const MIN_NOTE_LENGTH = 10;

    public function __construct(private ScheduleLifecycleService $lifecycle)
    {
    }

    /**
     * Bốn quy tắc về quyền và bối cảnh, kiểm tra trước khi cho ghi bất cứ thứ gì.
     */
    public function assertCanRecord(
        User $guide,
        TourSchedule $schedule,
        ItineraryCheckpoint $checkpoint,
        ?Carbon $now = null,
    ): void {
        $now ??= now();

        // 1. Chỉ hướng dẫn viên đang phụ trách chuyến này mới ghi được.
        // Khi task M02 vào, chỗ này đổi sang tra bảng phân công theo giai đoạn để hỗ trợ
        // thay hướng dẫn viên giữa chừng.
        if ((int) $schedule->guide_id !== (int) $guide->getKey()) {
            throw new BusinessRuleException(
                'Bạn không phụ trách chuyến đi này nên không điểm danh được.',
                403,
            );
        }

        // 2. Chuyến phải đang chạy. Dùng trạng thái theo đồng hồ chứ không đọc cột trong cơ sở
        // dữ liệu, vì tác vụ nền có thể chưa kịp chuyển trạng thái.
        $status = $this->lifecycle->effectiveStatus($schedule, $now);

        if (!$status->isRunning()) {
            throw new BusinessRuleException(sprintf(
                'Chỉ điểm danh được khi đoàn đang đi. Chuyến này đang ở trạng thái "%s".',
                $status->label(),
            ));
        }

        // 3. Điểm dừng phải thuộc lịch trình của đúng tour này.
        $checkpoint->loadMissing('tourItinerary');

        if ((int) $checkpoint->tourItinerary?->tour_id !== (int) $schedule->tour_id) {
            throw new BusinessRuleException('Điểm dừng không thuộc lịch trình của chuyến đi này.');
        }

        // 4. Không cho tick trước cho ngày chưa tới.
        $ngayCuaDiemDung = $this->checkpointDate($schedule, $checkpoint);

        if ($ngayCuaDiemDung->startOfDay()->gt($now->copy()->startOfDay())) {
            throw new BusinessRuleException(sprintf(
                'Điểm dừng này thuộc ngày %s, chưa tới nên chưa điểm danh được.',
                $ngayCuaDiemDung->format('d/m/Y'),
            ));
        }
    }

    /**
     * Ghi điểm danh cho một hành khách tại một điểm dừng.
     *
     * Ghi đè bản cũ thì lưu lịch sử chứ không xóa dấu vết, vì đây là dữ liệu dùng để quy trách
     * nhiệm khi có khiếu nại.
     */
    public function record(
        User $guide,
        TourSchedule $schedule,
        ItineraryCheckpoint $checkpoint,
        BookingPassenger $passenger,
        PassengerCheckinStatus $status,
        ?string $note = null,
        ?Carbon $now = null,
    ): PassengerCheckin {
        $now ??= now();

        $this->assertCanRecord($guide, $schedule, $checkpoint, $now);

        // 6. Hành khách phải thuộc một đơn còn hiệu lực của đúng chuyến này.
        $this->assertPassengerBelongsToSchedule($passenger, $schedule);

        // 7. Mọi trạng thái khác có mặt đều phải kèm lý do.
        if ($status->requiresNote() && mb_strlen(trim((string) $note)) < self::MIN_NOTE_LENGTH) {
            throw new BusinessRuleException(sprintf(
                'Trạng thái "%s" phải kèm ghi chú ít nhất %d ký tự để giải thích lý do.',
                $status->label(),
                self::MIN_NOTE_LENGTH,
            ));
        }

        // 5. Ghi bù muộn thì vẫn cho ghi nhưng phải đánh dấu, vì thực tế có lúc mất sóng.
        $isLateEntry = $now->gt(
            $this->checkpointDate($schedule, $checkpoint)->endOfDay()->addHours(self::LATE_ENTRY_AFTER_HOURS)
        );

        return DB::transaction(function () use (
            $guide, $schedule, $checkpoint, $passenger, $status, $note, $now, $isLateEntry
        ) {
            $checkin = PassengerCheckin::query()
                ->where('booking_passenger_id', $passenger->getKey())
                ->where('itinerary_checkpoint_id', $checkpoint->getKey())
                ->lockForUpdate()
                ->first();

            // 9. Sửa bản ghi đã có thì lưu lịch sử trước khi ghi đè.
            if ($checkin) {
                $trangThaiCu = $checkin->status;

                if ($trangThaiCu !== $status || $checkin->note !== $note) {
                    PassengerCheckinHistory::create([
                        'passenger_checkin_id' => $checkin->getKey(),
                        'old_status' => $trangThaiCu?->value,
                        'new_status' => $status->value,
                        'note' => $note,
                        'changed_by' => $guide->getKey(),
                        'changed_at' => $now,
                    ]);
                }

                $checkin->update([
                    'status' => $status,
                    'note' => $note,
                    'checked_by' => $guide->getKey(),
                    'checked_at' => $now,
                    'is_late_entry' => $isLateEntry,
                ]);

                return $checkin;
            }

            return PassengerCheckin::create([
                'booking_passenger_id' => $passenger->getKey(),
                'tour_schedule_id' => $schedule->getKey(),
                'itinerary_checkpoint_id' => $checkpoint->getKey(),
                'status' => $status,
                'note' => $note,
                'checked_by' => $guide->getKey(),
                'checked_at' => $now,
                'is_late_entry' => $isLateEntry,
            ]);
        });
    }

    /**
     * 8. Điểm dừng bắt buộc chụp ảnh thì phải có ảnh mới chốt được.
     *
     * Đây là cơ chế chống hướng dẫn viên ngồi nhà tick điểm danh. Ảnh chứng minh có mặt tại
     * điểm, và cũng là bằng chứng nếu về sau khách khiếu nại là đoàn không ghé điểm đó.
     */
    public function assertCheckpointCompletable(
        TourSchedule $schedule,
        ItineraryCheckpoint $checkpoint,
    ): void {
        if (!$checkpoint->is_required_photo) {
            return;
        }

        $coAnh = CheckpointPhoto::query()
            ->where('tour_schedule_id', $schedule->getKey())
            ->where('itinerary_checkpoint_id', $checkpoint->getKey())
            ->exists();

        if (!$coAnh) {
            throw new BusinessRuleException(sprintf(
                'Điểm dừng "%s" bắt buộc có ảnh check-in. Vui lòng tải ảnh lên trước khi chốt điểm danh.',
                $checkpoint->name,
            ));
        }
    }

    /**
     * Danh sách hành khách chưa được điểm danh tại một điểm dừng.
     *
     * Dùng cho ràng buộc mềm ở tài liệu 04 mục 5.4: không nên chuyển sang điểm dừng tiếp theo
     * khi còn người chưa được ghi nhận.
     *
     * @return \Illuminate\Support\Collection<int, BookingPassenger>
     */
    public function pendingPassengers(TourSchedule $schedule, ItineraryCheckpoint $checkpoint)
    {
        $daGhi = PassengerCheckin::query()
            ->where('itinerary_checkpoint_id', $checkpoint->getKey())
            ->pluck('booking_passenger_id');

        return BookingPassenger::query()
            ->whereHas('booking', function ($query) use ($schedule) {
                $query->where('tour_schedule_id', $schedule->getKey())
                    ->whereNotIn('status', ['cancelled', 'transferred']);
            })
            ->whereNotIn('id', $daGhi)
            ->get();
    }

    private function assertPassengerBelongsToSchedule(BookingPassenger $passenger, TourSchedule $schedule): void
    {
        $passenger->loadMissing('booking');
        $booking = $passenger->booking;

        if (!$booking || (int) $booking->tour_schedule_id !== (int) $schedule->getKey()) {
            throw new BusinessRuleException('Hành khách này không thuộc chuyến đi đang điểm danh.');
        }

        if (in_array($booking->status, ['cancelled', 'transferred'], true)) {
            throw new BusinessRuleException(
                'Đơn của hành khách này đã hủy hoặc đã chuyển chuyến nên không nằm trong danh sách đoàn.',
            );
        }
    }

    /**
     * Ngày thực tế của một điểm dừng trên chuyến này.
     * Điểm dừng chỉ biết nó thuộc ngày thứ mấy của lịch trình, phải cộng với ngày khởi hành
     * của chuyến mới ra ngày thật.
     */
    private function checkpointDate(TourSchedule $schedule, ItineraryCheckpoint $checkpoint): Carbon
    {
        $checkpoint->loadMissing('tourItinerary');

        $ngayThu = max(1, (int) ($checkpoint->tourItinerary?->day_number ?? 1));

        return Carbon::parse($schedule->start_date)->copy()->addDays($ngayThu - 1);
    }
}
