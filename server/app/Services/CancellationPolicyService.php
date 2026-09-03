<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingTransfer;
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
    public function __construct(private readonly BookingPaymentService $payments)
    {
    }

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
     * 3. `$congTyHuy` là lúc **công ty** đơn phương hủy đơn này, không phải khách đổi ý. Khi ấy
     *    hoàn đủ số đã thu, không áp bảng phí — cùng nguyên tắc mà luồng hủy cả chuyến đang dùng:
     *    bảng phí dành cho người đổi ý, còn đây là bên bán không thực hiện.
     *
     *    Trước khi có tham số này, màn hủy đơn của quản trị áp bảng phí nhưng lại ghi cứng
     *    `cancel_type = 'by_company'` lên đơn. Hai thứ ấy mâu thuẫn ngay trong một bản ghi, và thư
     *    báo hủy đọc đúng cột kia rồi khẳng định với khách là "không áp dụng phí hủy, hoàn đủ 100%"
     *    trong khi hệ thống vừa trừ của họ 30%.
     *
     * @param  iterable<int, array<string, mixed>|object>|null  $rules
     * @return array{hours_before: float|null, refund_percent: int, total_amount: float, paid_amount: float, cancellation_fee: float, refund_amount: float}
     */
    public function quote(
        Booking $booking,
        ?TourSchedule $schedule = null,
        ?iterable $rules = null,
        ?Carbon $now = null,
        bool $congTyHuy = false,
    ): array {
        $schedule ??= $booking->schedule;
        $rules ??= $this->rulesFor($booking);

        $hoursBefore = $this->hoursBeforeDeparture($schedule, $now);

        $doCongTyDoiNgay = $this->congTyDaDoiNgay($booking);
        $mienPhiHuy = $doCongTyDoiNgay || $congTyHuy;
        $refundPercent = $mienPhiHuy ? 100 : $this->refundPercent($hoursBefore, $rules);

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
            // Để màn hình nói được VÌ SAO mức hoàn là 100%, thay vì để người bấm tự đoán.
            'moved_by_company' => $doCongTyDoiNgay,
            // Hai lý do miễn phí hủy tách riêng: một cái hệ thống tự suy ra từ lịch sử chuyển
            // chuyến, một cái do người bấm chọn. Màn hình cần phân biệt để giải thích đúng.
            'company_initiated' => $congTyHuy,
            'fee_waived' => $mienPhiHuy,
        ];
    }

    /**
     * Đơn này có đang nằm trên một chuyến do CÔNG TY dời tới hay không.
     *
     * Nếu có thì hủy được hoàn đủ, bảng phí không áp. Khách mua ngày 20 mà công ty giao ngày 23,
     * họ từ chối ngày 23 thì đó không phải hủy đơn tự nguyện - và bắt họ chịu phí hủy cho một thay
     * đổi họ không hề chọn là thu tiền của người mình vừa làm phiền.
     *
     * Cùng chuẩn mà luồng hủy cả chuyến đã áp: ở đó khách được chọn "hoàn đủ tiền" hoặc "chuyển
     * chuyến miễn phí". Ghép chuyến là cùng một việc - chuyến nguồn bị hủy - chỉ khác là hệ thống
     * chọn hộ phương án chuyển. Nên quyền hoàn đủ phải còn nguyên.
     *
     * Đọc từ `booking_transfers`, không cần thêm cột: bảng ấy đã ghi ai khởi xướng và chuyển tới
     * chuyến nào. Điều kiện `to_schedule_id` khớp chuyến hiện tại là để một lần chuyển cũ đã bị
     * thay thế bởi lần chuyển sau (do khách xin) không còn tính nữa.
     */
    public function congTyDaDoiNgay(Booking $booking): bool
    {
        if (!$booking->tour_schedule_id) {
            return false;
        }

        return BookingTransfer::query()
            ->where('booking_id', $booking->getKey())
            ->where('initiated_by', 'company')
            ->where('to_schedule_id', $booking->tour_schedule_id)
            ->exists();
    }

    /**
     * Số tiền khách đã thực trả cho đơn này.
     *
     * Phép tính nằm ở `BookingPaymentService::paidForTour()` — nguồn duy nhất cho câu hỏi này, để
     * luồng hủy đơn, luồng hủy chuyến và bản in hợp đồng không bao giờ trả lời khác nhau.
     */
    private function paidAmount(Booking $booking): float
    {
        return $this->payments->paidForTour($booking);
    }
}
