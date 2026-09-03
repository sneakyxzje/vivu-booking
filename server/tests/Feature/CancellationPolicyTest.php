<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\TourSchedule;
use App\Services\CancellationPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B03 - Phí hủy và tiền hoàn.
 *
 * Câu hỏi số 7 của hội đồng: hủy tour phải trước bao lâu, và hoàn bao nhiêu.
 * Bảng phí ở docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 2.
 *
 * Toàn bộ phép tính là hàm thuần trên số giờ còn lại và số tiền, nên không cần cơ sở dữ liệu.
 */
class CancellationPolicyTest extends TestCase
{
    // quote() tra bảng chính sách trong cơ sở dữ liệu trước khi rơi về bảng phí trong mã,
    // nên cần schema. Không seed gì để các bài dưới chạy trên bảng mặc định.
    use RefreshDatabase;

    private CancellationPolicyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CancellationPolicyService::class);
    }

    private function schedule(int $gioNua): TourSchedule
    {
        return (new TourSchedule())->forceFill([
            'id' => 1,
            'start_date' => now()->addHours($gioNua),
        ]);
    }

    private function booking(float $tongTien, bool $daThanhToan = true): Booking
    {
        return (new Booking())->forceFill([
            'id' => 1,
            'total_amount' => $tongTien,
            'paid_at' => $daThanhToan ? now()->subDays(5) : null,
        ]);
    }

    public function test_dung_muc_hoan_o_tung_bac(): void
    {
        $bac = [
            [24 * 30, 100], // hủy rất sớm, chưa cam kết gì với nhà cung cấp
            [24 * 20, 100], // đúng mốc 20 ngày
            [24 * 19, 75],  // 19 ngày, giữ lại nửa tiền cọc
            [24 * 15, 75],  // đúng mốc 15 ngày
            [24 * 14, 50],  // 14 ngày, giữ trọn tiền cọc
            [24 * 12, 50],  // đúng mốc 12 ngày
            [24 * 11, 50],  // 11 ngày, mất nửa giá tour — cùng con số, khác lý do
            [24 * 10, 50],  // đúng hạn trả nốt: mất đúng phần đã cọc
            [24 * 8, 50],   // đúng mốc 8 ngày
            [24 * 7, 10],   // 7 ngày
            [24 * 2, 10],   // đúng mốc 2 ngày
            [47, 0],        // dưới 48 giờ
            [1, 0],         // sát giờ khởi hành
        ];

        foreach ($bac as [$gioNua, $mucHoan]) {
            $this->assertSame(
                $mucHoan,
                $this->service->refundPercent((float) $gioNua),
                "Còn {$gioNua} giờ thì phải hoàn {$mucHoan} phần trăm.",
            );
        }
    }

    /**
     * Đã qua giờ khởi hành thì không rơi vào quy tắc nào. Đây cũng là mức áp cho khách
     * không có mặt lúc khởi hành.
     */
    public function test_qua_gio_khoi_hanh_thi_khong_hoan(): void
    {
        $this->assertSame(0, $this->service->refundPercent(-1.0));
        $this->assertSame(0, $this->service->refundPercent(-240.0));
    }

    public function test_don_khong_gan_chuyen_thi_khong_tinh_duoc_so_gio(): void
    {
        $this->assertNull($this->service->hoursBeforeDeparture(null));
        $this->assertSame(0, $this->service->refundPercent(null));
    }

    public function test_tinh_day_du_mot_lan_huy_truoc_muoi_ngay(): void
    {
        $ketQua = $this->service->quote(
            $this->booking(10_000_000),
            $this->schedule(24 * 10),
        );

        /*
         * Còn 10 ngày rơi vào bậc 8 đến 12 ngày, hoàn 50 phần trăm.
         *
         * Đây cũng chính là hạn trả nốt mặc định, và con số 50 không phải ngẫu nhiên: nó bằng đúng
         * tỷ lệ cọc, nên khách bỏ ngang ở mốc này mất tròn phần đã đặt cọc.
         */
        $this->assertSame(50, $ketQua['refund_percent']);
        $this->assertSame(5_000_000.0, $ketQua['cancellation_fee']);
        $this->assertSame(5_000_000.0, $ketQua['refund_amount']);
    }

    /**
     * Phí hủy tính trên giá trị đơn, tiền hoàn trừ trên số đã thu. Khách mới đóng một phần
     * mà hủy sát ngày thì mất phần đã đóng, chứ không được hoàn theo tỷ lệ của phần đó.
     */
    public function test_phi_tinh_tren_gia_tri_don_khong_phai_tren_so_da_thu(): void
    {
        $ketQua = $this->service->quote(
            $this->booking(10_000_000),
            $this->schedule(24 * 3), // 3 ngày, hoàn 10 phần trăm
        );

        $this->assertSame(9_000_000.0, $ketQua['cancellation_fee']);
        $this->assertSame(1_000_000.0, $ketQua['refund_amount']);
    }

    /**
     * Ràng buộc quan trọng nhất: khách hủy thì không bao giờ phải nộp thêm tiền.
     */
    public function test_tien_hoan_khong_bao_gio_am(): void
    {
        $ketQua = $this->service->quote(
            $this->booking(10_000_000),
            $this->schedule(12), // dưới 48 giờ, hoàn 0 phần trăm
        );

        $this->assertSame(10_000_000.0, $ketQua['cancellation_fee']);
        $this->assertSame(0.0, $ketQua['refund_amount']);
    }

    public function test_don_chua_thanh_toan_thi_khong_co_gi_de_hoan(): void
    {
        $ketQua = $this->service->quote(
            $this->booking(10_000_000, daThanhToan: false),
            $this->schedule(24 * 30),
        );

        $this->assertSame(100, $ketQua['refund_percent']);
        $this->assertSame(0.0, $ketQua['paid_amount']);
        $this->assertSame(0.0, $ketQua['refund_amount']);
    }

    /**
     * Chính sách riêng của tour phải ghi đè được bảng mặc định. Khi task B01 vào, các quy tắc
     * này đến từ bảng cancellation_policy_rules thay vì mảng.
     */
    public function test_chinh_sach_rieng_ghi_de_bang_mac_dinh(): void
    {
        $quyTacRieng = [
            ['min_days_before' => 7, 'max_days_before' => null, 'refund_percent' => 100],
            ['min_days_before' => 0, 'max_days_before' => 7, 'refund_percent' => 20],
        ];

        $this->assertSame(100, $this->service->refundPercent(24 * 10, $quyTacRieng));
        $this->assertSame(20, $this->service->refundPercent(24 * 3, $quyTacRieng));
    }

    /**
     * Bậc đếm bằng ngày nhưng ranh giới nằm ở giờ, không làm tròn.
     *
     * Bảng phí ghi "dưới 2 ngày hoàn 0%". Người hủy trước 47 tiếng vẫn là dưới 2 ngày, và phải rơi
     * vào bậc ấy. Làm tròn số ngày lên thì họ được tính như đã báo trước đủ 2 ngày - một mức ưu ái
     * không có trong hợp đồng, phát sinh từ một phép làm tròn.
     */
    public function test_ranh_gioi_bac_tinh_theo_gio_khong_lam_tron_ngay(): void
    {
        $bac = [
            ['min_days_before' => 2, 'max_days_before' => null, 'refund_percent' => 60],
            ['min_days_before' => 0, 'max_days_before' => 2, 'refund_percent' => 0],
        ];

        $this->assertSame(0, $this->service->refundPercent(47.0, $bac), '47 giờ vẫn là dưới 2 ngày.');
        $this->assertSame(60, $this->service->refundPercent(48.0, $bac), 'Đúng 2 ngày thì vào bậc trên.');
        $this->assertSame(60, $this->service->refundPercent(49.0, $bac));
    }

    /**
     * Quy tắc đến từ cơ sở dữ liệu là đối tượng chứ không phải mảng. Lớp dịch vụ phải đọc
     * được cả hai để B01 vào không phải sửa gì ở đây.
     */
    public function test_doc_duoc_quy_tac_dang_doi_tuong(): void
    {
        $quyTac = [
            (object) ['min_days_before' => 5, 'max_days_before' => null, 'refund_percent' => 80],
            (object) ['min_days_before' => 0, 'max_days_before' => 5, 'refund_percent' => 10],
        ];

        $this->assertSame(80, $this->service->refundPercent(24 * 6, $quyTac));
        $this->assertSame(10, $this->service->refundPercent(24 * 2, $quyTac));
    }

    /**
     * Thứ tự ưu tiên: bản chính sách đơn đã sao chép vào chính nó, rồi bản đang có hiệu lực trong
     * cơ sở dữ liệu, cuối cùng mới tới bảng phí viết trong mã.
     *
     * Đây là chỗ nguyên tắc không hồi tố sống hay chết: đơn cũ vẫn hưởng bảng phí cũ dù bảng phí
     * mới đã áp dụng từ lâu.
     */
    public function test_ban_don_da_chep_thang_ban_dang_ap_dung(): void
    {
        $macDinh = CancellationPolicy::create([
            'name' => 'Mặc định',
            'effective_from' => now()->subDay(),
        ]);
        $macDinh->rules()->create([
            'min_days_before' => 0, 'max_days_before' => null, 'refund_percent' => 10,
        ]);

        $rieng = CancellationPolicy::create([
            'name' => 'Bản cũ hơn, đơn đã chép vào chính nó',
            'effective_from' => now()->subDays(30),
        ]);
        $rieng->rules()->create([
            'min_days_before' => 0, 'max_days_before' => null, 'refund_percent' => 55,
        ]);

        $booking = $this->booking(10_000_000);
        $booking->cancellation_policy_id = $rieng->id;
        $booking->setRelation('cancellationPolicy', $rieng);

        $ketQua = $this->service->quote($booking, $this->schedule(24 * 10));

        $this->assertSame(55, $ketQua['refund_percent']);
    }

    /**
     * Đơn chưa gắn chính sách thì dùng bản đang có hiệu lực trong cơ sở dữ liệu, không rơi
     * thẳng về bảng phí trong mã.
     */
    public function test_don_khong_gan_chinh_sach_thi_dung_ban_dang_ap_dung(): void
    {
        $macDinh = CancellationPolicy::create([
            'name' => 'Mặc định',
            'effective_from' => now()->subDay(),
        ]);
        $macDinh->rules()->create([
            'min_days_before' => 0, 'max_days_before' => null, 'refund_percent' => 25,
        ]);

        $ketQua = $this->service->quote($this->booking(10_000_000), $this->schedule(24 * 10));

        $this->assertSame(25, $ketQua['refund_percent']);
    }

    public function test_lam_tron_ve_dong_nguyen(): void
    {
        $ketQua = $this->service->quote(
            $this->booking(3_333_333),
            $this->schedule(24 * 10), // hoàn 50 phần trăm
        );

        // 50% của 3.333.333 là 1.666.666,5 — làm tròn lên đồng nguyên ở cả hai vế.
        $this->assertSame(1_666_667.0, $ketQua['cancellation_fee']);
        $this->assertSame(1_666_666.0, $ketQua['refund_amount']);
    }
}
