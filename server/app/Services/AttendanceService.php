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
use App\Notifications\PassengerAbsentAtBoundaryNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * H08 - Các quy tắc kiểm tra khi điểm danh.
 *
 * Chín quy tắc ở docs/nghiep-vu/04-luong-dieu-hanh.md mục 5.3,
 * gom về một chỗ vì cùng một thao tác điểm danh có thể tới từ
 * nhiều màn hình khác nhau.
 */
class AttendanceService
{
    /** Ghi bù sau khoảng này thì đánh dấu là ghi muộn. */
    private const LATE_ENTRY_AFTER_HOURS = 24;

    /** Ghi chú giải thích phải đủ dài để có ý nghĩa khi đọc lại. */
    private const MIN_NOTE_LENGTH = 10;

    public function __construct(
        private ScheduleLifecycleService $lifecycle
    ) {
    }

    /**
     * Bốn quy tắc về quyền và bối cảnh,
     * kiểm tra trước khi cho ghi bất cứ thứ gì.
     */
    public function assertCanRecord(
        User $guide,
        TourSchedule $schedule,
        ItineraryCheckpoint $checkpoint,
        ?Carbon $now = null,
    ): void {
        $now ??= now();

        // 1. Chỉ hướng dẫn viên đang phụ trách chuyến này mới ghi được.
        if ((int) $schedule->guide_id !== (int) $guide->getKey()) {
            throw new BusinessRuleException(
                'Bạn không phụ trách chuyến đi này nên không điểm danh được.',
                403,
            );
        }

        // 2. Chuyến phải đang chạy.
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
            throw new BusinessRuleException(
                'Điểm dừng không thuộc lịch trình của chuyến đi này.'
            );
        }

        // 4. Không cho tick trước cho ngày chưa tới.
        $ngayCuaDiemDung = $this->checkpointDate(
            $schedule,
            $checkpoint
        );

        if (
            $ngayCuaDiemDung->startOfDay()
                ->gt($now->copy()->startOfDay())
        ) {
            throw new BusinessRuleException(sprintf(
                'Điểm dừng này thuộc ngày %s, chưa tới nên chưa điểm danh được.',
                $ngayCuaDiemDung->format('d/m/Y'),
            ));
        }
    }

    /**
     * Ghi điểm danh cho một hành khách tại một điểm dừng.
     *
     * Ghi đè bản cũ thì lưu lịch sử chứ không xóa dấu vết.
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

        $this->assertCanRecord(
            $guide,
            $schedule,
            $checkpoint,
            $now
        );

        // 6. Hành khách phải thuộc một đơn còn hiệu lực
        // của đúng chuyến này.
        $this->assertPassengerBelongsToSchedule(
            $passenger,
            $schedule
        );

        // 7. Mọi trạng thái yêu cầu lý do
        // phải có ghi chú đủ dài.
        if (
            $status->requiresNote()
            && mb_strlen(trim((string) $note)) < self::MIN_NOTE_LENGTH
        ) {
            throw new BusinessRuleException(sprintf(
                'Trạng thái "%s" phải kèm ghi chú ít nhất %d ký tự để giải thích lý do.',
                $status->label(),
                self::MIN_NOTE_LENGTH,
            ));
        }

        // 5. Ghi bù muộn thì vẫn cho ghi
        // nhưng đánh dấu là ghi muộn.
        $isLateEntry = $now->gt(
            $this->checkpointDate(
                $schedule,
                $checkpoint
            )
                ->endOfDay()
                ->addHours(self::LATE_ENTRY_AFTER_HOURS)
        );

        /*
         * Lưu điểm danh.
         */
        $checkin = DB::transaction(function () use (
            $guide,
            $schedule,
            $checkpoint,
            $passenger,
            $status,
            $note,
            $now,
            $isLateEntry
        ) {
            $checkin = PassengerCheckin::query()
                ->where(
                    'booking_passenger_id',
                    $passenger->getKey()
                )
                ->where(
                    'itinerary_checkpoint_id',
                    $checkpoint->getKey()
                )
                ->lockForUpdate()
                ->first();

            /*
             * Nếu đã có bản ghi thì cập nhật
             * và lưu lịch sử thay đổi.
             */
            if ($checkin) {
                $trangThaiCu = $checkin->status;

                if (
                    $trangThaiCu !== $status
                    || $checkin->note !== $note
                ) {
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

                return $checkin->fresh();
            }

            /*
             * Chưa có thì tạo mới.
             */
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

        /*
         * H12
         *
         * Nếu hành khách vắng tại điểm đón đầu tiên
         * hoặc điểm cuối thì gửi thông báo cho admin.
         *
         * Đặt sau transaction để đảm bảo điểm danh
         * đã được lưu thành công trước khi gửi notification.
         */
        if ($status === PassengerCheckinStatus::Absent) {
            $this->notifyAdminIfBoundaryCheckpoint(
                $schedule,
                $checkpoint,
                $passenger,
            );
        }

        return $checkin;
    }

    /**
     * H08 - Điểm dừng bắt buộc chụp ảnh
     * thì phải có ảnh mới chốt được.
     */
    public function assertCheckpointCompletable(
        TourSchedule $schedule,
        ItineraryCheckpoint $checkpoint,
    ): void {
        if (!$checkpoint->is_required_photo) {
            return;
        }

        $coAnh = CheckpointPhoto::query()
            ->where(
                'tour_schedule_id',
                $schedule->getKey()
            )
            ->where(
                'itinerary_checkpoint_id',
                $checkpoint->getKey()
            )
            ->exists();

        if (!$coAnh) {
            throw new BusinessRuleException(sprintf(
                'Điểm dừng "%s" bắt buộc có ảnh check-in. Vui lòng tải ảnh lên trước khi chốt điểm danh.',
                $checkpoint->name,
            ));
        }
    }

    /**
     * Danh sách hành khách chưa được điểm danh
     * tại một điểm dừng.
     *
     * @return \Illuminate\Support\Collection<int, BookingPassenger>
     */
    public function pendingPassengers(
        TourSchedule $schedule,
        ItineraryCheckpoint $checkpoint
    ) {
        $daGhi = PassengerCheckin::query()
            ->where(
                'itinerary_checkpoint_id',
                $checkpoint->getKey()
            )
            ->pluck('booking_passenger_id');

        return BookingPassenger::query()
            ->whereHas('booking', function ($query) use ($schedule) {
                $query
                    ->where(
                        'tour_schedule_id',
                        $schedule->getKey()
                    )
                    ->whereNotIn(
                        'status',
                        ['cancelled', 'transferred']
                    );
            })
            ->whereNotIn('id', $daGhi)
            ->get();
    }

    /**
     * Kiểm tra hành khách có thuộc chuyến này hay không.
     */
    private function assertPassengerBelongsToSchedule(
        BookingPassenger $passenger,
        TourSchedule $schedule
    ): void {
        $passenger->loadMissing('booking');

        $booking = $passenger->booking;

        if (
            !$booking
            || (int) $booking->tour_schedule_id
                !== (int) $schedule->getKey()
        ) {
            throw new BusinessRuleException(
                'Hành khách này không thuộc chuyến đi đang điểm danh.'
            );
        }

        if (
            in_array(
                $booking->status,
                ['cancelled', 'transferred'],
                true
            )
        ) {
            throw new BusinessRuleException(
                'Đơn của hành khách này đã hủy hoặc đã chuyển chuyến nên không nằm trong danh sách đoàn.',
            );
        }
    }

    /**
     * H12 - Báo cho điều hành khi khách vắng
     * tại điểm đón đầu tiên hoặc điểm cuối của hành trình.
     *
     * Quy trình:
     * 1. Lấy toàn bộ checkpoint của tour.
     * 2. Sắp xếp theo ngày -> thứ tự checkpoint.
     * 3. Xác định checkpoint đầu tiên và cuối cùng.
     * 4. Nếu checkpoint hiện tại không phải đầu/cuối thì bỏ qua.
     * 5. Nếu là đầu/cuối thì gửi notification cho tất cả admin.
     */
    private function notifyAdminIfBoundaryCheckpoint(
        TourSchedule $schedule,
        ItineraryCheckpoint $checkpoint,
        BookingPassenger $passenger,
    ): void {
        $checkpoints = ItineraryCheckpoint::query()
            ->whereHas('tourItinerary', function ($query) use ($schedule) {
                $query->where(
                    'tour_id',
                    $schedule->tour_id
                );
            })
            ->with('tourItinerary:id,day_number')
            ->get([
                'id',
                'tour_itinerary_id',
                'sequence',
            ])
            ->sortBy([
                ['tourItinerary.day_number', 'asc'],
                ['sequence', 'asc'],
            ])
            ->values();

        if ($checkpoints->isEmpty()) {
            return;
        }

        $firstCheckpoint = $checkpoints->first();
        $lastCheckpoint = $checkpoints->last();

        $isFirstCheckpoint =
            (int) $checkpoint->id
            === (int) $firstCheckpoint->id;

        $isLastCheckpoint =
            (int) $checkpoint->id
            === (int) $lastCheckpoint->id;

        /*
         * Không phải điểm đầu cũng không phải điểm cuối
         * thì không gửi notification.
         */
        if (!$isFirstCheckpoint && !$isLastCheckpoint) {
            return;
        }

        /*
         * Tìm tất cả tài khoản admin.
         */
        User::query()
            ->where('role', 'admin')
            ->get()
            ->each(function (User $admin) use (
                $schedule,
                $checkpoint,
                $passenger,
                $isFirstCheckpoint
            ) {
                $admin->notify(
                    new PassengerAbsentAtBoundaryNotification(
                        $schedule,
                        $passenger,
                        $checkpoint,
                        $isFirstCheckpoint,
                    )
                );
            });
    }

    /**
     * Ngày thực tế của một điểm dừng trên chuyến này.
     *
     * Điểm dừng chỉ biết nó thuộc ngày thứ mấy
     * của lịch trình, phải cộng với ngày khởi hành
     * của chuyến mới ra ngày thật.
     */
    private function checkpointDate(
        TourSchedule $schedule,
        ItineraryCheckpoint $checkpoint
    ): Carbon {
        $checkpoint->loadMissing('tourItinerary');

        $ngayThu = max(
            1,
            (int) ($checkpoint->tourItinerary?->day_number ?? 1)
        );

        return Carbon::parse($schedule->start_date)
            ->copy()
            ->addDays($ngayThu - 1);
    }
}