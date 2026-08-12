<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\TourSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Máy trạng thái của lịch khởi hành.
 *
 * Mọi lần đổi trạng thái chuyến đều phải đi qua đây, không gọi thẳng $schedule->update(['status' => ...]).
 * Có một cửa duy nhất thì mới đảm bảo được ba thứ: đường chuyển hợp lệ, ghi đủ cột truy vết,
 * và không có hai luồng cùng đổi trạng thái đè lên nhau.
 */
class ScheduleLifecycleService
{
    /**
     * Đọc trạng thái hiện tại dưới dạng enum.
     *
     * Chấp nhận cả giá trị cũ ('active', 'inactive', 'full') để dịch vụ này chạy được trước
     * khi migration A01 đổi kiểu cột. Sau khi A01 và A03 vào, nhánh chuỗi gần như không còn
     * chạy tới nữa vì model đã cast sẵn sang enum.
     */
    public function currentStatus(TourSchedule $schedule): ScheduleStatus
    {
        $raw = $this->rawStatus($schedule);

        if ($raw instanceof ScheduleStatus) {
            return $raw;
        }

        $value = (string) $raw;

        return ScheduleStatus::tryFrom($value)
            ?? ScheduleStatus::fromLegacy($value, $this->hasDeparted($schedule));
    }

    private function rawStatus(TourSchedule $schedule): ScheduleStatus|string
    {
        $attributes = $schedule->getAttributes();

        if (array_key_exists('status', $attributes)) {
            return $attributes['status'];
        }

        $raw = $schedule->getRawOriginal('status');

        if ($raw !== null) {
            return $raw;
        }

        return $schedule->status;
    }

    /**
     * Ném lỗi nếu đường chuyển không hợp lệ. Chuyển về chính trạng thái đang có thì bỏ qua,
     * để các lệnh chạy nền gọi lại nhiều lần vẫn an toàn.
     */
    public function assertCanTransition(ScheduleStatus $from, ScheduleStatus $to): void
    {
        if ($from === $to) {
            return;
        }

        if (!$from->canTransitionTo($to)) {
            throw new BusinessRuleException(sprintf(
                'Không thể chuyển lịch khởi hành từ trạng thái "%s" sang "%s".',
                $from->label(),
                $to->label(),
            ));
        }
    }

    /**
     * Đổi trạng thái chuyến và ghi các cột truy vết đi kèm.
     *
     * Khóa dòng rồi đọc lại trạng thái trước khi kiểm tra là điểm mấu chốt: nếu hai luồng cùng
     * gọi hàm này, luồng vào sau phải thấy trạng thái đã đổi và bị từ chối, chứ không được
     * kiểm tra trên bản đọc cũ rồi ghi đè kết quả của luồng trước.
     */
    public function transitionTo(
        TourSchedule $schedule,
        ScheduleStatus $to,
        ?string $reason = null,
        ?int $actorId = null,
    ): TourSchedule {
        $updated = DB::transaction(function () use ($schedule, $to, $reason, $actorId) {
            $locked = TourSchedule::query()
                ->whereKey($schedule->getKey())
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                throw new BusinessRuleException('Không tìm thấy lịch khởi hành.', 404);
            }

            $from = $this->currentStatus($locked);

            $this->assertCanTransition($from, $to);

            if ($from === $to) {
                return $locked;
            }

            // forceFill thay cho update vì các cột truy vết chỉ được thêm vào fillable ở A03.
            // Payload dựng hoàn toàn từ tham số của hàm, không nhận dữ liệu người dùng, nên
            // bỏ qua mass assignment ở đây là an toàn.
            $locked->forceFill($this->payloadFor($to, $reason, $actorId))->save();

            return $locked;
        });

        $schedule->setRawAttributes($updated->getAttributes(), true);

        return $schedule;
    }

    /**
     * Trạng thái thực tế của chuyến tại thời điểm này.
     *
     * Khác currentStatus ở chỗ có tính tới đồng hồ. Trạng thái lưu trong cơ sở dữ liệu có thể
     * chậm hơn thực tế khi tác vụ nền chưa kịp chạy hoặc bị dừng: một chuyến khởi hành từ hôm
     * qua vẫn có thể còn ghi 'open'. Các quy tắc chặn phải dựa vào trạng thái thực tế, nếu không
     * chỉ cần cron chết là mọi ràng buộc theo trạng thái thủng theo.
     */
    public function effectiveStatus(TourSchedule $schedule, ?Carbon $now = null): ScheduleStatus
    {
        $current = $this->currentStatus($schedule);

        if ($current->isFinal()) {
            return $current;
        }

        $now ??= now();

        if ($this->endMoment($schedule)?->lt($now)) {
            return ScheduleStatus::Completed;
        }

        if ($this->startMoment($schedule)?->lte($now)) {
            return ScheduleStatus::InProgress;
        }

        return $current;
    }

    /**
     * Trạng thái mà chuyến lẽ ra phải có tính theo thời gian hiện tại.
     * Trả về null khi không cần đổi gì. Dùng cho lệnh chạy nền cập nhật trạng thái theo lịch.
     */
    public function resolveStatusByTime(TourSchedule $schedule, ?Carbon $now = null): ?ScheduleStatus
    {
        $now ??= now();
        $current = $this->currentStatus($schedule);

        // isFinal() đã bao gồm Cancelled và Completed.
        if ($current->isFinal()) {
            return null;
        }

        if ($this->endMoment($schedule)?->lt($now) && $current === ScheduleStatus::InProgress) {
            return ScheduleStatus::Completed;
        }

        if ($current === ScheduleStatus::Confirmed && $this->startMoment($schedule)?->lte($now)) {
            return ScheduleStatus::InProgress;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(ScheduleStatus $to, ?string $reason, ?int $actorId): array
    {
        $payload = ['status' => $to->value];

        if ($to === ScheduleStatus::Confirmed) {
            $payload['confirmed_at'] = now();
        }

        if ($to === ScheduleStatus::Cancelled) {
            $payload['cancelled_at'] = now();
            $payload['cancelled_by'] = $actorId;
            $payload['cancelled_reason'] = $reason;
        }

        return $payload;
    }

    private function hasDeparted(TourSchedule $schedule): bool
    {
        return (bool) $this->startMoment($schedule)?->isPast();
    }

    private function startMoment(TourSchedule $schedule): ?Carbon
    {
        return $schedule->start_date ? Carbon::parse($schedule->start_date) : null;
    }

    /**
     * Ngày kết thúc thật của chuyến. Cột end_date chỉ có sau migration A01, nên khi chưa có
     * thì suy từ số ngày của tour để dịch vụ vẫn dùng được.
     */
    private function endMoment(TourSchedule $schedule): ?Carbon
    {
        if (!empty($schedule->end_date)) {
            return Carbon::parse($schedule->end_date);
        }

        $start = $this->startMoment($schedule);

        if (!$start) {
            return null;
        }

        $days = (int) ($schedule->tour?->number_of_days ?? 1);

        return $start->copy()->addDays(max(0, $days - 1))->endOfDay();
    }
}


