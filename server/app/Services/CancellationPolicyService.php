<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\TourSchedule;
use Illuminate\Support\Carbon;

/**
 * B03 - Tính phí hủy và số tiền hoàn cho một đơn đặt tour.
 *
 * Quy tắc và cơ sở của các mốc xem docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 2.
 *
 * Vì sao phí tính theo bậc thời gian chứ không theo tỷ lệ đều: chi phí của một chuyến không
 * phát sinh đều mà nhảy bậc tại các mốc chốt với nhà cung cấp. Khách sạn chốt phòng khoảng
 * 7 ngày trước, nhà xe chốt 3 ngày, suất ăn chốt 1 đến 2 ngày. Khách hủy càng sát thì phần
 * chi phí hãng đã cam kết mà không hủy được càng lớn.
 */
class CancellationPolicyService
{
    /**
     * Bảng phí hủy mặc định, dùng khi đơn chưa gắn chính sách riêng.
     *
     * Đây là thông lệ thị trường lữ hành nội địa, không phải trích từ một văn bản cụ thể.
     * Xem ghi chú về mức độ tin cậy ở docs/nghiep-vu/00-pham-vi-va-gioi-han.md mục 5.
     *
     * @var array<int, array{min_hours_before: int, max_hours_before: int|null, refund_percent: int}>
     */
    public const DEFAULT_RULES = [
        ['min_hours_before' => 360, 'max_hours_before' => null, 'refund_percent' => 90], // từ 15 ngày
        ['min_hours_before' => 192, 'max_hours_before' => 360, 'refund_percent' => 70],  // 8 đến 14 ngày
        ['min_hours_before' => 96, 'max_hours_before' => 192, 'refund_percent' => 50],   // 4 đến 7 ngày
        ['min_hours_before' => 48, 'max_hours_before' => 96, 'refund_percent' => 30],    // 2 đến 3 ngày
        ['min_hours_before' => 0, 'max_hours_before' => 48, 'refund_percent' => 0],      // dưới 48 giờ
    ];

    /**
     * Quy tắc áp cho một đơn cụ thể.
     *
     * Thứ tự ưu tiên: chính sách đã sao chép vào đơn lúc đặt, rồi tới chính sách mặc định
     * trong cơ sở dữ liệu, cuối cùng mới tới bảng phí viết trong mã.
     *
     * Đọc từ đơn chứ không đọc qua tour là điểm mấu chốt: sửa chính sách của tour về sau
     * không được làm đổi điều khoản mà khách đã đồng ý khi đặt.
     *
     * @return iterable<int, array<string, mixed>|object>
     */
    public function rulesFor(Booking $booking): iterable
    {
        $booking->loadMissing('cancellationPolicy.rules');

        $rules = $booking->cancellationPolicy?->rules;

        if ($rules && $rules->isNotEmpty()) {
            return $rules;
        }

        $macDinh = CancellationPolicy::default();

        if ($macDinh && $macDinh->rules->isNotEmpty()) {
            return $macDinh->rules;
        }

        return self::DEFAULT_RULES;
    }

    /**
     * Số giờ còn lại tới lúc khởi hành. Âm nghĩa là đã qua giờ khởi hành.
     * Trả về null khi đơn không gắn chuyến nào.
     */
    public function hoursBeforeDeparture(?TourSchedule $schedule, ?Carbon $now = null): ?float
    {
        if (!$schedule?->start_date) {
            return null;
        }

        $now ??= now();

        return $now->floatDiffInHours(Carbon::parse($schedule->start_date), false);
    }

    /**
     * Phần trăm được hoàn ứng với số giờ còn lại.
     *
     * Đã qua giờ khởi hành thì $hoursBefore âm, không rơi vào quy tắc nào nên hoàn 0.
     * Đây cũng là mức áp cho khách không có mặt lúc khởi hành.
     *
     * @param  iterable<int, array<string, mixed>|object>|null  $rules
     */
    public function refundPercent(?float $hoursBefore, ?iterable $rules = null): int
    {
        if ($hoursBefore === null) {
            return 0;
        }

        $matched = null;

        foreach ($rules ?? self::DEFAULT_RULES as $rule) {
            $min = (float) data_get($rule, 'min_hours_before', 0);
            $max = data_get($rule, 'max_hours_before');

            if ($hoursBefore < $min) {
                continue;
            }

            if ($max !== null && $hoursBefore >= (float) $max) {
                continue;
            }

            // Nhiều quy tắc chồng nhau thì lấy quy tắc có mốc dưới cao nhất.
            if ($matched === null || $min > (float) data_get($matched, 'min_hours_before', 0)) {
                $matched = $rule;
            }
        }

        return (int) data_get($matched, 'refund_percent', 0);
    }

    /**
     * Bảng tính đầy đủ cho một lần hủy: còn bao lâu, hoàn bao nhiêu phần trăm,
     * phí hủy bao nhiêu và khách thực nhận lại bao nhiêu.
     *
     * Hai điểm nghiệp vụ dễ nhầm:
     *
     * 1. Phí hủy tính trên GIÁ TRỊ ĐƠN, còn tiền hoàn trừ trên SỐ ĐÃ THU. Tiền cọc là khoản
     *    đảm bảo cho cam kết, nên khách mới đóng cọc mà hủy sát ngày thì mất cọc, đúng bản chất.
     *
     * 2. Kẹp dưới bằng 0. Khách hủy thì không bao giờ phải nộp thêm, kể cả khi phí hủy lớn hơn
     *    số đã thu.
     *
     * @param  iterable<int, array<string, mixed>|object>|null  $rules
     * @return array{hours_before: float|null, refund_percent: int, total_amount: float, paid_amount: float, cancellation_fee: float, refund_amount: float}
     */
    public function quote(
        Booking $booking,
        ?TourSchedule $schedule = null,
        ?iterable $rules = null,
        ?Carbon $now = null,
    ): array {
        $schedule ??= $booking->schedule;
        $rules ??= $this->rulesFor($booking);

        $hoursBefore = $this->hoursBeforeDeparture($schedule, $now);
        $refundPercent = $this->refundPercent($hoursBefore, $rules);

        $totalAmount = round((float) $booking->total_amount);
        $paidAmount = $this->paidAmount($booking);

        $cancellationFee = round($totalAmount * (100 - $refundPercent) / 100);
        $refundAmount = max(0.0, $paidAmount - $cancellationFee);

        return [
            'hours_before' => $hoursBefore,
            'refund_percent' => $refundPercent,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'cancellation_fee' => $cancellationFee,
            'refund_amount' => $refundAmount,
        ];
    }

    /**
     * Số tiền khách đã thực trả cho đơn này.
     *
     * Hiện hệ thống chỉ có thanh toán một lần nên đã trả là trả đủ. Khi sổ giao dịch đơn hàng
     * vào (task N01), thay thân hàm này bằng tổng các giao dịch thu đã thành công, phần còn lại
     * của lớp dịch vụ không phải sửa.
     */
    private function paidAmount(Booking $booking): float
    {
        return $booking->paid_at ? round((float) $booking->total_amount) : 0.0;
    }
}
