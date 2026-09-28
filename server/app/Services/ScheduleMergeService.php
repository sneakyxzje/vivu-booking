<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Enums\ProposalStatus;
use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Exceptions\BusinessRuleException;
use App\Mail\BookingProposalMail;
use App\Mail\BookingTransferredMail;
use App\Models\Booking;
use App\Models\BookingChangeProposal;
use App\Models\BookingTransfer;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;


class ScheduleMergeService
{
    public const MAX_DAY_GAP = 2;

    public function __construct(
        private ScheduleLifecycleService $lifecycle,
        private BookingAuditLogger $auditLogger,
    ) {}

    public function preview(TourSchedule $from, TourSchedule $to): array
    {
        $reason = null;
        try {
            $this->assertCanMerge($from, $to);
        } catch (BusinessRuleException $e) {
            $reason = $e->getMessage();
        }
        $bookings = $this->bookingsToTransfer($from);
        $seats = (int) $bookings->sum(fn (Booking $booking) => $booking->seatsTaken());
        return [
            'can_merge' => $reason === null, 'blocked_reason' => $reason,
            'transferring' => $bookings->count(),
            'transferring_guests' => (int) $bookings->sum('guests'),
            'transferring_seats' => $seats, 'cancelling' => 0,
            'requires_consent' => true,
            'response_deadline' => $this->responseDeadline($from, $to)->toDateTimeString(),
            'remaining_seats' => $to->remainingSeats(),
            'remaining_seats_after' => $to->remainingSeats() - $seats,
        ];
    }

    private function responseDeadline(TourSchedule $from, TourSchedule $to): Carbon
    {
        return DemoClock::schedule($from)->addDays(2)
            ->min($from->booking_deadline ?? $from->defaultBookingDeadline())
            ->min($to->booking_deadline ?? $to->defaultBookingDeadline());
    }

    private function snapshot(TourSchedule $schedule): array
    {
        return [
            'tour_id' => $schedule->tour_id, 'tour_title' => $schedule->tour?->title,
            'tour_slug' => $schedule->tour?->slug,
            'pickup_location' => $schedule->tour?->pickup_location,
            'vehicle_info' => $schedule->tour?->vehicle_info,
            'itineraries' => $schedule->tour?->itineraries()->orderBy('day_number')
                ->get(['day_number', 'title', 'start_point', 'end_point', 'route_points', 'rest_stops', 'content'])->toArray() ?? [],
            'start_date' => $schedule->start_date?->toDateTimeString(),
            'end_date' => $schedule->end_date?->toDateTimeString(),
            'arrival_at' => $schedule->arrival_at?->toDateTimeString(),
            'return_departure_at' => $schedule->return_departure_at?->toDateTimeString(),
            'booking_deadline' => ($schedule->booking_deadline ?? $schedule->defaultBookingDeadline())?->toDateTimeString(),
        ];
    }

    public function merge(TourSchedule $from, TourSchedule $to, string $reason, ?User $actor = null): array
    {
        if (!$actor) {
            throw new BusinessRuleException('Cần người điều hành tạo đề xuất ghép chuyến.');
        }
        return DB::transaction(function () use ($from, $to, $reason, $actor) {
            $schedules = TourSchedule::query()->whereIn('id', [$from->id, $to->id])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $schedules->get($from->id);
            $target = $schedules->get($to->id);
            if (!$source || !$target) {
                throw new BusinessRuleException('Không tìm thấy chuyến.', 404);
            }
            $this->assertCanMerge($source, $target);
            $count = 0;
            foreach ($this->bookingsToTransfer($source) as $row) {
                $booking = Booking::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
                $email = $booking->customer_email ?: $booking->customer?->email;
                if (!$email) {
                    throw new BusinessRuleException("Đơn #{$booking->id} chưa có email nhận đề xuất.");
                }
                $snapshot = ['from' => $this->snapshot($source), 'to' => $this->snapshot($target)];
                $existing = $booking->proposals()->pending()->where('to_schedule_id', $target->id)
                    ->where('response_deadline', '>', DemoClock::booking($booking))->first();
                if ($existing && $existing->schedule_snapshot == $snapshot) {
                    continue;
                }
                $booking->proposals()->pending()->update(['status' => ProposalStatus::Expired->value]);
                $proposal = BookingChangeProposal::create([
                    'booking_id' => $booking->id, 'admin_id' => $actor->id,
                    'from_schedule_id' => $source->id, 'to_schedule_id' => $target->id,
                    'schedule_snapshot' => $snapshot, 'reason' => $reason,
                    'response_deadline' => $this->responseDeadline($source, $target),
                    'status' => ProposalStatus::Pending->value,
                ]);
                Mail::to($email)->queue((new BookingProposalMail($booking, $proposal))->afterCommit());
                $count++;
            }
            return ['proposed' => $count, 'transferred' => 0, 'cancelled' => 0];
        });
    }

    public function respond(BookingChangeProposal $proposal, string $action, ?string $note): BookingChangeProposal
    {
        return DB::transaction(function () use ($proposal, $action, $note) {
            $schedules = TourSchedule::query()->whereIn('id', array_filter([$proposal->from_schedule_id, $proposal->to_schedule_id]))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $booking = Booking::query()->whereKey($proposal->booking_id)->lockForUpdate()->firstOrFail();
            $locked = BookingChangeProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ProposalStatus::Pending) {
                throw new BusinessRuleException('Đề xuất đã được xử lý hoặc hết hạn.');
            }
            if (DemoClock::booking($booking)->gte($locked->response_deadline)) {
                $locked->update(['status' => ProposalStatus::Expired->value]);
                return $locked;
            }
            if ($action === 'accept' && $locked->schedule_snapshot) {
                $source = $schedules->get($locked->from_schedule_id);
                $target = $schedules->get($locked->to_schedule_id);
                if (!$source || !$target || (int) $booking->tour_schedule_id !== (int) $source->id
                    || !in_array($booking->status, BookingStatus::paidValues(), true)) {
                    throw new BusinessRuleException('Đơn hoặc chuyến đã thay đổi; cần đề xuất mới. Chuyến hiện tại được giữ nguyên.');
                }
                // MySQL JSON có thể đổi thứ tự khóa object; so nội dung, không so thứ tự khóa.
                if ($locked->schedule_snapshot != ['from' => $this->snapshot($source), 'to' => $this->snapshot($target)]) {
                    throw new BusinessRuleException('Lịch trình hoặc hạn thanh toán đã thay đổi; cần đề xuất mới.');
                }
                $this->assertCanMerge($source, $target, $booking->seatsTaken());
                $seats = $booking->seatsTaken();
                $booking->forceFill([
                    'tour_id' => $target->tour_id, 'tour_schedule_id' => $target->id,
                    'departure_date' => $target->start_date,
                    'transfer_count' => (int) $booking->transfer_count + 1,
                    'balance_reminder_sent_at' => null, 'balance_final_notice_at' => null,
                ])->save();
                $source->decrement('booked_people', min($seats, (int) $source->booked_people));
                $target->increment('booked_people', $seats);
                $transfer = BookingTransfer::create([
                    'booking_id' => $booking->id,
                    'from_schedule_id' => $source->id, 'to_schedule_id' => $target->id,
                    'from_tour_id' => $source->tour_id, 'to_tour_id' => $target->tour_id,
                    'initiated_by' => 'company', 'price_difference' => 0, 'fee' => 0,
                    'reason' => $locked->reason, 'approved_by' => $locked->admin_id,
                    'approved_at' => DemoClock::schedule($source),
                ]);
                $this->auditLogger->log($booking, BookingAuditAction::Transferred,
                    ['tour_schedule_id' => $source->id],
                    ['tour_schedule_id' => $target->id, 'proposal_id' => $locked->id, 'customer_accepted' => true], $locked->reason);
                if (!$source->bookings()->whereIn('status', [...BookingStatus::paidValues(), BookingStatus::Pending->value])->exists()) {
                    $source->forceFill(['merged_into_schedule_id' => $target->id])->save();
                    $this->lifecycle->transitionTo($source, ScheduleStatus::Cancelled,
                        'Mọi khách đã đồng ý chuyển chuyến; chuyến nguồn không còn đơn đang hoạt động.', $locked->admin_id);
                }
                $email = $booking->customer_email ?: $booking->customer?->email;
                if ($email) {
                    Mail::to($email)->queue((new BookingTransferredMail($transfer))->afterCommit());
                }
            }
            $locked->update([
                'status' => $action === 'accept' ? ProposalStatus::Accepted->value : ProposalStatus::Rejected->value,
                'customer_note' => $note, 'responded_at' => DemoClock::booking($booking),
            ]);
            return $locked;
        });
    }

    private function bookingsToTransfer(TourSchedule $from)
    {
        return $from->bookings()->whereIn('status', BookingStatus::paidValues())->get();
    }

    public function assertCanMerge(TourSchedule $from, TourSchedule $to, ?int $seats = null): void
    {
        if ((int) $from->getKey() === (int) $to->getKey()) {
            throw new BusinessRuleException('Chuyến nguồn và chuyến đích trùng nhau.');
        }

        // 1. Cùng tour. Ghép hai tour khác nhau là đổi hẳn sản phẩm khách đã mua.
        // Ngoại lệ: Nếu 2 chuyến KHÁC tour nhưng khởi hành CÙNG NGÀY CÙNG GIỜ, hệ thống cho phép ghép.
        // Điều hành sẽ tự chịu trách nhiệm tổ chức và giữ nguyên dịch vụ đã cam kết.
        if ((int) $from->tour_id !== (int) $to->tour_id) {
            $cungGio = $from->start_date && $to->start_date && $from->start_date->equalTo($to->start_date);
            if (!$cungGio) {
                throw new BusinessRuleException('Chỉ ghép được hai chuyến của cùng một tour, hoặc hai chuyến khác tour nhưng phải khởi hành cùng ngày và cùng giờ.');
            }
        }

        if ($from->is_private || $to->is_private || $to->tour?->type === 'private' || $to->tour?->status !== 'active') {
            throw new BusinessRuleException('Tour riêng hoặc tour không hoạt động không ghép được.');
        }

        $loai = TourType::tryFrom((string) ($from->tour?->type ?? TourType::Shared->value));

        if ($loai && !$loai->canMergeSchedules()) {
            throw new BusinessRuleException(
                'Tour riêng không ghép chuyến được, vì khách đã trả tiền để đi trọn chuyến của riêng họ.',
            );
        }

        // 2. Cả hai phải đang mở bán. Chuyến đã đóng bán hoặc chốt chạy không được xáo trộn.
        foreach ([$from, $to] as $schedule) {
            $trangThai = $this->lifecycle->effectiveStatus($schedule);

            if ($trangThai !== ScheduleStatus::Open) {
                throw new BusinessRuleException(sprintf(
                    'Chuyến #%d đang ở trạng thái "%s" (không phải Đang mở bán) nên không ghép được.',
                    $schedule->getKey(),
                    $trangThai->label(),
                ));
            }
        }


        foreach ([['chuyến nguồn', $from], ['chuyến đích', $to]] as [$ten, $schedule]) {
            $hanChot = $schedule->booking_deadline ?? $schedule->defaultBookingDeadline();

            if ($hanChot && DemoClock::schedule($schedule)->gte($hanChot)) {
                throw new BusinessRuleException(sprintf(
                    'Đã qua hạn chốt danh sách của %s (#%d) ngày %s. Danh sách đã gửi nhà cung cấp '
                        . 'nên không ghép được nữa; chuyến chưa đạt mục tiêu vẫn phải tổ chức cho khách.',
                    $ten,
                    $schedule->getKey(),
                    Carbon::parse($hanChot)->format('d/m/Y H:i'),
                ));
            }
        }

        // 3. Chuyến đích còn đủ chỗ cho toàn bộ khách của chuyến nguồn.
        $canChuyen = $seats ?? (int) $this->bookingsToTransfer($from)->sum(fn (Booking $don) => $don->seatsTaken());
        $conTrong = (int) $to->max_people - (int) $to->booked_people;

        if ($conTrong < $canChuyen) {
            throw new BusinessRuleException(sprintf(
                'Chuyến đích chỉ còn %d chỗ, không đủ cho %d khách của chuyến nguồn.',
                max(0, $conTrong),
                $canChuyen,
            ));
        }

        // 4. Ngày khởi hành không lệch quá xa. Đổi ngày xa hơn ảnh hưởng lớn tới kế hoạch của
        // khách, khách được quyền từ chối phương án ghép.
        if ($from->start_date && $to->start_date) {
            $lech = abs(Carbon::parse($from->start_date)->diffInDays(Carbon::parse($to->start_date)));

            if ($lech > self::MAX_DAY_GAP) {
                throw new BusinessRuleException(sprintf(
                    'Hai chuyến lệch nhau %d ngày, vượt ngưỡng %d ngày cho phép khi ghép.',
                    (int) $lech,
                    self::MAX_DAY_GAP,
                ));
            }
        }
    }

}
