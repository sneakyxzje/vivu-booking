<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\TourSchedule;
use App\Services\CancellationPolicyService;
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
            [24 * 20, 90], // 20 ngày, từ 15 ngày trở lên
            [24 * 15, 90], // đúng mốc 15 ngày
            [24 * 14, 70], // 14 ngày
            [24 * 8, 70],  // đúng mốc 8 ngày
            [24 * 7, 50],  // 7 ngày
            [24 * 4, 50],  // đúng mốc 4 ngày
            [24 * 3, 30],  // 3 ngày
            [24 * 2, 30],  // đúng mốc 2 ngày
            [47, 0],       // dưới 48 giờ
            [1, 0],        // sát giờ khởi hành
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

        // Còn 10 ngày rơi vào bậc 8 đến 14 ngày, hoàn 70 phần trăm.
        $this->assertSame(70, $ketQua['refund_percent']);
        $this->assertSame(3_000_000.0, $ketQua['cancellation_fee']);
        $this->assertSame(7_000_000.0, $ketQua['refund_amount']);
    }

    /**
     * Phí hủy tính trên giá trị đơn, tiền hoàn trừ trên số đã thu. Khách mới đóng một phần
     * mà hủy sát ngày thì mất phần đã đóng, chứ không được hoàn theo tỷ lệ của phần đó.
     */
    public function test_phi_tinh_tren_gia_tri_don_khong_phai_tren_so_da_thu(): void
    {
        $ketQua = $this->service->quote(
            $this->booking(10_000_000),
            $this->schedule(24 * 3), // 3 ngày, hoàn 30 phần trăm
        );

        $this->assertSame(7_000_000.0, $ketQua['cancellation_fee']);
        $this->assertSame(3_000_000.0, $ketQua['refund_amount']);
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

        $this->assertSame(90, $ketQua['refund_percent']);
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
            ['min_hours_before' => 168, 'max_hours_before' => null, 'refund_percent' => 100],
            ['min_hours_before' => 0, 'max_hours_before' => 168, 'refund_percent' => 20],
        ];

        $this->assertSame(100, $this->service->refundPercent(24 * 10, $quyTacRieng));
        $this->assertSame(20, $this->service->refundPercent(24 * 3, $quyTacRieng));
    }

    /**
     * Quy tắc đến từ cơ sở dữ liệu là đối tượng chứ không phải mảng. Lớp dịch vụ phải đọc
     * được cả hai để B01 vào không phải sửa gì ở đây.
     */
    public function test_doc_duoc_quy_tac_dang_doi_tuong(): void
    {
        $quyTac = [
            (object) ['min_hours_before' => 100, 'max_hours_before' => null, 'refund_percent' => 80],
            (object) ['min_hours_before' => 0, 'max_hours_before' => 100, 'refund_percent' => 10],
        ];

        $this->assertSame(80, $this->service->refundPercent(150.0, $quyTac));
        $this->assertSame(10, $this->service->refundPercent(50.0, $quyTac));
    }

    public function test_lam_tron_ve_dong_nguyen(): void
    {
        $ketQua = $this->service->quote(
            $this->booking(3_333_333),
            $this->schedule(24 * 10), // hoàn 70 phần trăm
        );

        $this->assertSame(1_000_000.0, $ketQua['cancellation_fee']);
        $this->assertSame(2_333_333.0, $ketQua['refund_amount']);
    }
}
