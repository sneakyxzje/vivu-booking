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
     * @var array<int, array{min_days_before: int, max_days_before: int|null, refund_percent: int}>
     */
    public const DEFAULT_RULES = [
        ['min_days_before' => 15, 'max_days_before' => null, 'refund_percent' => 90],
        ['min_days_before' => 8, 'max_days_before' => 15, 'refund_percent' => 70],
        ['min_days_before' => 4, 'max_days_before' => 8, 'refund_percent' => 50],
        ['min_days_before' => 2, 'max_days_before' => 4, 'refund_percent' => 30],
        ['min_days_before' => 0, 'max_days_before' => 2, 'refund_percent' => 0],
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

        $dangApDung = CancellationPolicy::dangApDung();

        if ($dangApDung && $dangApDung->rules->isNotEmpty()) {
            return $dangApDung->rules;
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
     * Nhận vào **giờ** nhưng so bằng **ngày**, và số ngày ở đây có phần lẻ chứ không làm tròn.
     * Hủy trước 36 tiếng là 1,5 ngày, rơi đúng vào bậc "dưới 2 ngày" - làm tròn xuống 1 ngày cũng
     * ra cùng bậc, nhưng làm tròn lên thì một người hủy trước 47 tiếng lại được tính như đã báo
     * trước 2 ngày. Phần lẻ giữ cho ranh giới nằm đúng chỗ hợp đồng ghi.
     *
     * Đã qua giờ khởi hành thì $hoursBefore âm, không rơi vào quy tắc nào nên hoàn 0. Đây cũng là
     * mức áp cho khách không có mặt lúc khởi hành.
     *
     * @param  iterable<int, array<string, mixed>|object>|null  $rules
     */
    public function refundPercent(?float $hoursBefore, ?iterable $rules = null): int
    {
        if ($hoursBefore === null) {
            return 0;
        }

        $soNgay = $hoursBefore / 24;
        $matched = null;

        foreach ($rules ?? self::DEFAULT_RULES as $rule) {
            $min = (float) data_get($rule, 'min_days_before', 0);
            $max = data_get($rule, 'max_days_before');

            if ($soNgay < $min) {
                continue;
            }

            if ($max !== null && $soNgay >= (float) $max) {
                continue;
            }

            // Nhiều quy tắc chồng nhau thì lấy quy tắc có mốc dưới cao nhất.
            if ($matched === null || $min > (float) data_get($matched, 'min_days_before', 0)) {
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
     * Hai nguồn, chọn theo đơn:
     *
     *   - Đơn có sổ giao dịch (trả nhiều đợt): tổng thu trừ tổng hoàn. Đây là chỗ làm cho phép
     *     tính "mất cọc" chạy đúng - khách mới đóng cọc 30% mà hủy sát ngày thì tiền hoàn trừ
     *     trên số cọc đã thu, không phải trên tổng giá trị đơn.
     *   - Đơn không có dòng nào trong sổ: đọc theo mốc paid_at như cũ. Nhánh này còn dùng cho các
     *     đơn tạo trước khi sổ mở cho đơn lẻ.
     *
     * Phân nhánh theo "sổ có dòng hay không" chứ không theo loại đơn — đúng như ghi chú cũ dự
     * liệu, và nhờ vậy việc mở sổ cho đơn lẻ không phải sửa gì ở đây.
     *
     * **Chỉ đếm dòng của giá tour.** Từ khi phụ thu sự cố cũng ghi vào sổ, một đơn lẻ đã trả đủ
     * qua cổng vẫn có thể có dòng trong sổ - dòng thu tiền một đêm phòng chạy bão. Nếu câu hỏi
     * "sổ có dòng chưa" đếm cả dòng ấy thì nhánh trên nhận đơn lẻ, cộng các loại THU ra 0, và
     * một đơn đã trả đủ bỗng báo đã thu 0 đồng - hủy đơn thì hoàn 0.
     *
     * Nên cả câu hỏi lẫn phép cộng đều lọc theo cùng một tập loại. Hai chỗ lệch nhau chính là
     * cách lỗi này sinh ra.
     */
    private function paidAmount(Booking $booking): float
    {
        $giaTour = $booking->payments()->whereIn('kind', array_merge(
            \App\Models\BookingPayment::THU,
            [\App\Models\BookingPayment::HOAN],
        ));

        if ((clone $giaTour)->exists()) {
            $thu = (float) $booking->payments()
                ->whereIn('kind', \App\Models\BookingPayment::THU)
                ->sum('amount');
            $hoan = (float) $booking->payments()
                ->where('kind', \App\Models\BookingPayment::HOAN)
                ->sum('amount');

            return round($thu - $hoan);
        }

        return $booking->paid_at ? round((float) $booking->total_amount) : 0.0;
    }
}
