<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingContract;
use App\Services\BookingPaymentService;
use App\Support\SoTienBangChu;
use Illuminate\Contracts\View\View;

/**
 * Trang in hợp đồng.
 *
 * Là tuyến web chứ không phải tuyến API, và mở bằng **liên kết có chữ ký** thay vì token: một thẻ
 * <a> không gắn được tiêu đề Authorization, mà hợp đồng thì phải mở được trong tab mới để in.
 * Chữ ký do `URL::temporarySignedRoute` sinh, hết hạn sau 24 giờ.
 *
 * Không có gì được ghi ở đây — mở bao nhiêu lần cũng ra cùng một văn bản, vì số hợp đồng đã cấp
 * từ trước và mọi số liệu còn lại đọc từ đơn.
 */
class ContractPrintController extends Controller
{
    public function __invoke(BookingContract $contract): View
    {
        $booking = $contract->booking()->with([
            'tour.itineraries' => fn ($q) => $q->orderBy('day_number'),
            'tour.services',
            'schedule',
            'cancellationPolicy.rules' => fn ($q) => $q->orderByDesc('min_hours_before'),
        ])->firstOrFail();

        $tour = $booking->tour;
        $tongTien = round((float) $booking->total_amount);
        $giamGia = round((float) ($booking->discount_amount ?? 0));
        $daThu = $this->daThu($booking);

        return view('contracts.tour', [
            'contract' => $contract,
            'booking' => $booking,
            'tour' => $tour,
            'congTy' => config('company'),
            'ngayCap' => $contract->issued_at,
            /*
             * Ép về Carbon tại đây, vì `Booking` cố ý không cast `departure_date` và `paid_at`
             * sang datetime — thêm cast vào model bây giờ sẽ đổi cách chúng được tuần tự hóa ở
             * hàng chục màn hình khác. Mẫu in cần đối tượng ngày, nên chuyển ở đúng chỗ cần.
             */
            'khoiHanh' => $this->ngay($booking->schedule?->start_date ?? $booking->departure_date),
            'ketThuc' => $this->ngay($booking->schedule?->end_date),
            'daThanhToanLuc' => $this->ngay($booking->paid_at),

            /*
             * Điều 4 cần ba con số, theo tài liệu 05 mục 2.2 điểm 7: đã thu, còn phải trả, và
             * hạn trả nốt.
             *
             * Đã thu đọc từ sổ giao dịch nếu đơn có sổ (đơn đoàn trả nhiều đợt), còn đơn lẻ thì
             * đọc mốc `paid_at` — đúng cách `CancellationPolicyService::paidAmount()` đang làm,
             * và cùng lý do: hai chỗ trả lời khác nhau cho câu "đơn này đã thu bao nhiêu" là thứ
             * không ai phát hiện cho tới lúc đối chiếu khiếu nại.
             *
             * CỐ Ý bỏ qua các dòng phụ thu sự cố: đó là tiền của chuyện khác, không phải tiền
             * trả cho tour.
             */
            'daThu' => $daThu,
            'conPhaiTra' => max(0.0, $tongTien - $daThu),
            /*
             * Hạn chốt danh sách làm hạn thanh toán. Đó là mốc điều hành phải trả tiền cho khách
             * sạn và nhà xe, nên cũng là mốc muộn nhất tiền của khách phải về.
             */
            'hanThanhToan' => $this->ngay($booking->schedule?->booking_deadline),
            'dongGia' => $this->dongGia($booking, $tour),
            'giamGia' => $giamGia,
            'tongTien' => $tongTien,
            'tongTienBangChu' => SoTienBangChu::doc($tongTien),
            'chinhSach' => $booking->cancellationPolicy,
            'bacHoan' => $booking->cancellationPolicy?->rules ?? collect(),
        ]);
    }

    /**
     * Số tiền đã thu cho GIÁ TOUR.
     *
     * Chỉ đếm bút toán của giá tour, không đếm phụ thu sự cố. Trộn vào thì hợp đồng ghi khách đã
     * trả nhiều hơn thực tế trả cho tour.
     *
     * Phép tính nằm ở `BookingPaymentService::paidForTour()`, cùng nguồn với bảng phí hủy và luồng
     * hủy chuyến — hợp đồng in ra không được nói một con số khác với màn hình kế toán.
     */
    private function daThu(Booking $booking): float
    {
        return app(BookingPaymentService::class)->paidForTour($booking);
    }

    /** Nhận Carbon, chuỗi hoặc null; trả về Carbon hoặc null. */
    private function ngay(mixed $gia): ?\Carbon\Carbon
    {
        if ($gia === null || $gia === '') {
            return null;
        }

        return $gia instanceof \DateTimeInterface
            ? \Carbon\Carbon::instance($gia)
            : \Carbon\Carbon::parse((string) $gia);
    }

    /**
     * Bảng giá theo từng loại khách.
     *
     * Đọc `adult_count` / `child_count` / `infant_count` của đơn chứ không chia đều `total_amount`
     * cho số khách: ba loại khách ba mức giá, chia đều thì hợp đồng ghi sai đơn giá.
     *
     * Đơn cũ không tách loại khách thì gộp thành một dòng, chấp nhận đơn giá là giá trung bình -
     * thà đọc được còn hơn in ra ba dòng đều bằng không.
     *
     * @return array<int, array{label: string, count: int, unit: float, total: float}>
     */
    private function dongGia($booking, $tour): array
    {
        $loai = [
            ['label' => 'Người lớn', 'count' => (int) ($booking->adult_count ?? 0), 'unit' => (float) ($tour->adult_price ?? 0)],
            ['label' => 'Trẻ em', 'count' => (int) ($booking->child_count ?? 0), 'unit' => (float) ($tour->child_price ?? 0)],
            ['label' => 'Em bé', 'count' => (int) ($booking->infant_count ?? 0), 'unit' => (float) ($tour->infant_price ?? 0)],
        ];

        $dong = [];
        foreach ($loai as $mot) {
            if ($mot['count'] > 0) {
                $dong[] = $mot + ['total' => $mot['count'] * $mot['unit']];
            }
        }

        if ($dong === []) {
            $khach = max(1, (int) $booking->guests);
            $tong = round((float) $booking->total_amount);

            $dong[] = [
                'label' => 'Khách',
                'count' => $khach,
                'unit' => round($tong / $khach),
                'total' => $tong,
            ];
        }

        return $dong;
    }
}
