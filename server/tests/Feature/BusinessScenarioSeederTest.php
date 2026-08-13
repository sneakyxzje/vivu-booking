<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Services\BookingFinalizationService;
use App\Services\BookingHoldService;
use App\Services\CancellationPolicyService;
use App\Services\ScheduleLifecycleService;
use Database\Seeders\BusinessScenarioSeeder;
use Database\Seeders\CancellationPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dữ liệu thử tay phải đúng như bảng tình huống seeder in ra.
 *
 * Vì sao cần kiểm thử cho một seeder: dữ liệu mẫu sai còn tệ hơn không có. Người thử tay tin
 * vào nhãn "hoàn 90%" rồi thấy màn hình ra 70% sẽ đi tìm lỗi trong mã ứng dụng, trong khi lỗi
 * nằm ở mốc thời gian của chính dữ liệu mẫu. Bộ này khóa từng con số trong bảng đó lại.
 */
class BusinessScenarioSeederTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CancellationPolicySeeder::class);
        $this->seed(BusinessScenarioSeeder::class);

        $this->tour = Tour::query()->where('slug', 'tour-thu-nghiem-nghiep-vu')->firstOrFail();
    }

    /** Chuyến thứ n theo thứ tự tạo, tức đúng thứ tự S1..S9 trong seeder. */
    private function chuyen(int $thuTu): TourSchedule
    {
        return TourSchedule::query()
            ->where('tour_id', $this->tour->id)
            ->orderBy('id')
            ->skip($thuTu - 1)
            ->firstOrFail();
    }

    private function donDauTien(TourSchedule $schedule, string $chuaTrongGhiChu): Booking
    {
        return Booking::query()
            ->where('tour_schedule_id', $schedule->id)
            ->where('note', 'like', '%' . $chuaTrongGhiChu . '%')
            ->firstOrFail();
    }

    public function test_dung_du_chin_chuyen_phu_het_sau_trang_thai(): void
    {
        $schedules = TourSchedule::query()->where('tour_id', $this->tour->id)->get();

        $this->assertCount(9, $schedules);

        $trangThai = $schedules
            ->map(fn (TourSchedule $s) => $s->getRawOriginal('status'))
            ->unique()
            ->values()
            ->all();

        foreach (ScheduleStatus::cases() as $case) {
            $this->assertContains(
                $case->value,
                $trangThai,
                "Thiếu chuyến ở trạng thái {$case->value}, không thử tay được trạng thái này.",
            );
        }
    }

    /**
     * Bài quan trọng nhất của bộ này. Năm mốc phí hủy phải ra đúng năm con số, nếu không thì
     * người thử tay đối chiếu với bảng trong tài liệu sẽ thấy lệch mà không hiểu vì sao.
     */
    public function test_nam_chuyen_dau_roi_dung_nam_bac_phi_huy(): void
    {
        $service = app(CancellationPolicyService::class);

        $mongDoi = [1 => 90, 2 => 70, 3 => 50, 4 => 30, 5 => 0];

        foreach ($mongDoi as $thuTu => $phanTram) {
            $schedule = $this->chuyen($thuTu);
            $booking = $this->donDauTien($schedule, 'Hủy thử');

            $quote = $service->quote($booking, $schedule);

            $this->assertSame(
                $phanTram,
                $quote['refund_percent'],
                "Chuyến S{$thuTu} phải rơi vào bậc hoàn {$phanTram}%.",
            );
        }
    }

    /**
     * Hai cổng đặt ở hai mốc khác nhau: tiền theo bậc giờ, chỗ theo hạn chốt 72 giờ. S3 còn
     * trước hạn chốt nên chỗ trả về, S4 đã qua hạn nên sinh ghế chết - dù cả hai đều là đơn đã
     * thanh toán và chỉ cách nhau vài chục giờ.
     */
    public function test_hai_cong_tien_va_cho_dat_o_hai_moc_khac_nhau(): void
    {
        $holdService = app(BookingHoldService::class);

        $truocHanChot = $this->chuyen(3);
        $sauHanChot = $this->chuyen(4);

        $this->assertTrue(
            $holdService->shouldReleaseSeats(
                $this->donDauTien($truocHanChot, 'Hủy thử'),
                $truocHanChot,
            ),
            'S3 còn trước hạn chốt nên hủy phải trả chỗ về kho.',
        );

        $this->assertFalse(
            $holdService->shouldReleaseSeats(
                $this->donDauTien($sauHanChot, 'Hủy thử'),
                $sauHanChot,
            ),
            'S4 đã qua hạn chốt nên hủy phải sinh ghế chết.',
        );
    }

    public function test_co_san_mot_ghe_chet_de_man_mo_lai_cho_co_du_lieu(): void
    {
        $gheChet = Booking::query()
            ->where('tour_id', $this->tour->id)
            ->withHeldSeats()
            ->get();

        $this->assertCount(1, $gheChet, 'Phải có đúng một ghế chết dựng sẵn.');
        $this->assertNotNull($gheChet->first()->cancelled_at);
    }

    /** Đối chứng: đơn chưa thanh toán thì luôn trả chỗ, kể cả đã qua hạn chốt. */
    public function test_don_chua_thanh_toan_qua_han_van_tra_cho(): void
    {
        $schedule = $this->chuyen(5);
        $booking = $this->donDauTien($schedule, 'release-expired');

        $this->assertTrue($booking->isOverdue(), 'Đơn phải đã quá hạn thanh toán.');
        $this->assertTrue(
            app(BookingHoldService::class)->shouldReleaseSeats($booking, $schedule),
            'Đơn chưa vào danh sách đoàn thì phải trả chỗ dù đã qua hạn chốt.',
        );
    }

    public function test_chuyen_dang_chay_va_da_ket_thuc_deu_chan_huy(): void
    {
        $lifecycle = app(ScheduleLifecycleService::class);

        $this->assertTrue(
            $lifecycle->effectiveStatus($this->chuyen(6))->blocksCancellation(),
            'S6 phải đang chạy để thử chặn hủy.',
        );

        $this->assertTrue(
            $lifecycle->effectiveStatus($this->chuyen(7))->blocksCancellation(),
            'S7 phải đã kết thúc.',
        );
    }

    /**
     * Chuyến đã kết thúc dựng ba tình trạng bằng chứng khác nhau, và lệnh chốt đơn phải ra đúng
     * một đơn không có mặt trên tổng ba đơn. Nếu ra hai thì luật kết luận đang quá tay.
     */
    public function test_chot_don_cua_chuyen_da_xong_ra_dung_mot_don_khong_co_mat(): void
    {
        $ketQua = app(BookingFinalizationService::class)->finalizeSchedule($this->chuyen(7));

        $this->assertSame(1, $ketQua['no_show'], 'Chỉ đơn có đủ bằng chứng vắng mới thành no_show.');
        $this->assertSame(2, $ketQua['completed']);

        $this->assertSame(
            1,
            Booking::query()
                ->where('tour_schedule_id', $this->chuyen(7)->id)
                ->where('status', BookingStatus::NoShow->value)
                ->count(),
        );
    }

    public function test_chuyen_thieu_khach_khong_du_muc_toi_thieu(): void
    {
        $schedule = $this->chuyen(9);

        $this->assertLessThan(
            (int) $schedule->min_people,
            (int) $schedule->booked_people,
            'S9 phải thiếu khách để lệnh chốt chuyến cảnh báo.',
        );
    }

    /**
     * Số chỗ ghi trên chuyến phải khớp số chỗ thực tế bị chiếm, theo đúng công thức của lệnh
     * đối chiếu. Seed lệch số thì lần chạy lệnh đầu tiên đã báo đỏ, và người thử tay không còn
     * phân biệt được đâu là lỗi thật đâu là dữ liệu mẫu ẩu.
     */
    public function test_so_cho_da_ban_khop_voi_so_cho_thuc_te_bi_chiem(): void
    {
        foreach (TourSchedule::query()->where('tour_id', $this->tour->id)->get() as $schedule) {
            $thucTe = (int) Booking::query()
                ->where('tour_schedule_id', $schedule->id)
                ->where(function ($query) {
                    $query->where('status', '!=', 'cancelled')
                        ->orWhere(function ($gheChet) {
                            $gheChet->where('status', 'cancelled')
                                ->where('seats_released', false);
                        });
                })
                ->sum('guests');

            $this->assertSame(
                $thucTe,
                (int) $schedule->booked_people,
                "Chuyến #{$schedule->id} lệch số chỗ ngay từ dữ liệu mẫu.",
            );
        }
    }

    /** Không có điểm dừng thì màn điểm danh rỗng và trông như tính năng chưa chạy. */
    public function test_moi_ngay_hanh_trinh_deu_co_diem_dung_va_co_toa_do(): void
    {
        $itineraries = $this->tour->itineraries()->with('checkpoints')->get();

        $this->assertCount(3, $itineraries);

        foreach ($itineraries as $itinerary) {
            $this->assertGreaterThanOrEqual(1, $itinerary->checkpoints->count());

            foreach ($itinerary->checkpoints as $checkpoint) {
                $this->assertNotNull(
                    $checkpoint->latitude,
                    'Điểm dừng thiếu tọa độ thì luồng tải ảnh check-in từ chối ngay.',
                );
            }
        }
    }

    public function test_seed_lai_khong_nhan_doi_du_lieu(): void
    {
        $this->seed(BusinessScenarioSeeder::class);

        $this->assertSame(
            1,
            Tour::query()->where('slug', 'tour-thu-nghiem-nghiep-vu')->count(),
        );

        $this->assertSame(
            9,
            TourSchedule::query()
                ->whereIn('tour_id', Tour::query()->where('slug', 'tour-thu-nghiem-nghiep-vu')->pluck('id'))
                ->count(),
        );
    }
}
