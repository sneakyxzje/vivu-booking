<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Danh sách đơn của điều hành: tìm, lọc, sắp xếp.
 *
 * Điều bộ test này giữ, và cũng là lỗi nó vá: mọi phép tìm và sắp xếp phải chạy trên TOÀN BỘ dữ
 * liệu, không phải trên mười dòng của trang đang xem. Nên gần như bài nào cũng dựng hơn một trang
 * đơn rồi hỏi về thứ nằm ở trang sau - trên một trang thì cách cài cũ cũng xanh.
 */
class AdminBookingListTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private Tour $tour;
    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dieuHanh = User::create([
            'name' => 'Dieu Hanh Test',
            'email' => 'dieu-hanh-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->tour = Tour::factory()->create([
            'title' => 'Tour Ha Long Ba Ngay',
            'status' => 'active',
            'type' => TourType::Shared->value,
            'adult_price' => 2_000_000,
        ]);

        $start = now()->addDays(20);

        $this->chuyen = TourSchedule::factory()->create([
            'tour_id' => $this->tour->id,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 200,
            'status' => ScheduleStatus::Open->value,
        ]);

        Sanctum::actingAs($this->dieuHanh);
    }

    /** Dựng đủ đơn để tràn sang trang thứ hai (mặc định 10 đơn một trang). */
    private function dungMotTrangRuoi(int $soLuong = 14): void
    {
        Booking::factory()
            ->count($soLuong)
            ->choChuyen($this->chuyen)
            ->soKhach(1)
            ->daThanhToan()
            ->create(['customer_name' => 'Khach Doan Dong']);
    }

    private function danhSach(array $thamSo = []): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/admin/bookings?' . http_build_query($thamSo));
    }

    // --- Tìm kiếm ------------------------------------------------------------------------

    /**
     * Bài quan trọng nhất của tệp này.
     *
     * Cách cài cũ tải mười đơn mới nhất rồi lọc chính mười dòng ấy ở trình duyệt, nên một đơn nằm ở
     * trang sau là "không tìm thấy" - và người dùng kết luận đơn không tồn tại.
     */
    public function test_tim_duoc_don_khong_nam_o_trang_dau(): void
    {
        $cu = Booking::factory()
            ->choChuyen($this->chuyen)
            ->soKhach(2)
            ->daThanhToan()
            ->create(['customer_name' => 'Nguyen Thi Kim Chi']);

        // Dựng sau nên id lớn hơn, đẩy đơn của chị Chi xuống tận trang hai.
        $this->dungMotTrangRuoi();

        $res = $this->danhSach(['q' => 'kim chi'])->assertOk();

        $this->assertSame(1, $res->json('data.total'));
        $this->assertSame($cu->id, $res->json('data.data.0.id'));
    }

    /**
     * Gõ đúng mã đơn thì ra đúng một đơn, không kèm gì khác.
     *
     * Người gõ "BK-19" là người đã biết mình tìm gì. Trả kèm mọi đơn có số 19 nằm đâu đó trong số
     * điện thoại là biến một phép tra cứu chính xác thành một danh sách phải đọc lại.
     */
    public function test_tim_theo_ma_don_hien_thi_tren_man_hinh(): void
    {
        $this->dungMotTrangRuoi();

        $don = Booking::factory()
            ->choChuyen($this->chuyen)
            ->soKhach(1)
            ->daThanhToan()
            ->create(['customer_name' => 'Tran Van Manh']);

        foreach (['BK-' . $don->id, 'bk' . $don->id, 'BK ' . $don->id] as $tuKhoa) {
            $res = $this->danhSach(['q' => $tuKhoa])->assertOk();

            $this->assertSame(1, $res->json('data.total'), "Gõ '{$tuKhoa}' phải ra đúng một đơn.");
            $this->assertSame($don->id, $res->json('data.data.0.id'));
        }
    }

    /**
     * Gõ trần một con số thì nhận cả hai nghĩa: mã đơn và mẩu số điện thoại.
     *
     * Số ấy có thể là đơn #15, cũng có thể là bốn số cuối khách đọc qua điện thoại. Chọn hộ một
     * nghĩa rồi giấu nghĩa kia đi là kiểu tìm kiếm bắt người dùng phải đoán ý máy.
     */
    public function test_go_tran_mot_con_so_thi_nhan_ca_ma_don_lan_so_dien_thoai(): void
    {
        $theoMa = Booking::factory()
            ->choChuyen($this->chuyen)
            ->soKhach(1)
            ->daThanhToan()
            ->create(['customer_name' => 'Dang Thi Lan', 'customer_phone' => '0988000111']);

        $theoSoDienThoai = Booking::factory()
            ->choChuyen($this->chuyen)
            ->soKhach(1)
            ->daThanhToan()
            ->create(['customer_name' => 'Vu Minh Quan', 'customer_phone' => '09' . $theoMa->id . '777888']);

        $ids = $this->danhSach(['q' => (string) $theoMa->id])->assertOk()->json('data.data.*.id');

        $this->assertContains($theoMa->id, $ids, 'Con số ấy là mã đơn.');
        $this->assertContains($theoSoDienThoai->id, $ids, 'Con số ấy cũng nằm trong một số điện thoại.');
    }

    /** Tìm theo tên tour: điều hành nhớ tuyến chứ ít khi nhớ tên khách. */
    public function test_tim_theo_ten_tour(): void
    {
        $tourKhac = Tour::factory()->create(['title' => 'Tour Sapa Hai Ngay', 'status' => 'active']);
        $chuyenKhac = TourSchedule::factory()->create([
            'tour_id' => $tourKhac->id,
            'max_people' => 50,
            'status' => ScheduleStatus::Open->value,
        ]);

        $this->dungMotTrangRuoi();

        $don = Booking::factory()->choChuyen($chuyenKhac)->soKhach(1)->daThanhToan()->create();

        $res = $this->danhSach(['q' => 'Sapa'])->assertOk();

        $this->assertSame(1, $res->json('data.total'));
        $this->assertSame($don->id, $res->json('data.data.0.id'));
    }

    // --- Sắp xếp -------------------------------------------------------------------------

    public function test_sap_xep_theo_tong_tien_tren_toan_bo_danh_sach(): void
    {
        // Đơn to nhất dựng đầu tiên nên id nhỏ nhất: theo thứ tự mặc định nó nằm ở trang cuối.
        $donTo = Booking::factory()
            ->choChuyen($this->chuyen)
            ->soKhach(1)
            ->daThanhToan()
            ->create(['customer_name' => 'Khach Doan Lon', 'total_amount' => 99_000_000]);

        $this->dungMotTrangRuoi();

        $res = $this->danhSach(['sort' => 'amount-desc'])->assertOk();

        $this->assertSame(
            $donTo->id,
            $res->json('data.data.0.id'),
            'Sắp theo tổng tiền phải lấy đơn to nhất của cả danh sách, không phải của trang đang xem.',
        );

        $tangDan = $this->danhSach(['sort' => 'amount-asc'])->assertOk();
        $this->assertNotSame($donTo->id, $tangDan->json('data.data.0.id'));
    }

    /** Hai đơn cùng số tiền vẫn phải ra thứ tự ổn định, nếu không phân trang sẽ lặp và bỏ sót đơn. */
    public function test_cung_so_tien_thi_thu_tu_van_on_dinh(): void
    {
        Booking::factory()
            ->count(25)
            ->choChuyen($this->chuyen)
            ->soKhach(1)
            ->daThanhToan()
            ->create(['total_amount' => 2_000_000]);

        $trang1 = $this->danhSach(['sort' => 'amount-desc', 'page' => 1])->json('data.data.*.id');
        $trang2 = $this->danhSach(['sort' => 'amount-desc', 'page' => 2])->json('data.data.*.id');

        $this->assertEmpty(
            array_intersect($trang1, $trang2),
            'Không đơn nào được xuất hiện ở cả hai trang.',
        );
    }

    // --- Bộ lọc --------------------------------------------------------------------------

    public function test_loc_theo_trang_thai_dem_tren_toan_bo_khong_phai_trang_dang_xem(): void
    {
        $this->dungMotTrangRuoi();

        Booking::factory()
            ->count(12)
            ->choChuyen($this->chuyen)
            ->soKhach(1)
            ->create(['status' => BookingStatus::Cancelled->value]);

        $res = $this->danhSach(['status' => 'cancelled'])->assertOk();

        $this->assertSame(12, $res->json('data.total'));
        $this->assertCount(10, $res->json('data.data'), 'Vẫn phân trang 10 dòng một trang.');
        $this->assertSame(12, $res->json('data.summary.total'), 'Ô thống kê phải nói về cả bộ lọc.');
        $this->assertSame(12, $res->json('data.summary.cancelled'));
    }

    /**
     * "Đã thanh toán" phải tính cả tiền chuyển khoản do điều hành ghi tay.
     *
     * Bộ lọc cũ đọc `vnpay_transaction_no`, nên mọi đơn thu ngoài cổng đều bị xếp vào "chưa thanh
     * toán" - bộ lọc nói sai về chính số tiền đã nằm trong tài khoản công ty.
     */
    public function test_da_thanh_toan_tinh_ca_khoan_thu_ngoai_cong(): void
    {
        $chuyenKhoan = Booking::factory()
            ->choChuyen($this->chuyen)
            ->soKhach(1)
            ->daThanhToan()
            ->create(['vnpay_transaction_no' => null]);

        Booking::factory()
            ->count(3)
            ->choChuyen($this->chuyen)
            ->soKhach(1)
            ->dangGiuCho()
            ->create();

        $daTra = $this->danhSach(['payment' => 'paid'])->assertOk();
        $chuaTra = $this->danhSach(['payment' => 'unpaid'])->assertOk();

        $this->assertSame(1, $daTra->json('data.total'));
        $this->assertSame($chuyenKhoan->id, $daTra->json('data.data.0.id'));
        $this->assertSame(3, $chuaTra->json('data.total'));
    }

    public function test_bo_loc_va_tim_kiem_dung_chung_mot_ket_qua(): void
    {
        $this->dungMotTrangRuoi();

        Booking::factory()
            ->count(4)
            ->choChuyen($this->chuyen)
            ->soKhach(1)
            ->create([
                'customer_name' => 'Le Hoang Yen',
                'status' => BookingStatus::Cancelled->value,
            ]);

        $res = $this->danhSach(['q' => 'Hoang Yen', 'status' => 'cancelled'])->assertOk();

        $this->assertSame(4, $res->json('data.total'));
        $this->assertSame(4, $res->json('data.summary.total'));
        $this->assertSame(0, $res->json('data.summary.confirmed'));
    }

    /** Tham số lạ thì từ chối thẳng, không lặng lẽ trả về danh sách sai. */
    public function test_kieu_sap_xep_khong_hop_le_bi_tu_choi(): void
    {
        $this->danhSach(['sort' => 'total_amount; drop table bookings'])->assertStatus(422);
        $this->danhSach(['status' => 'khong-co-that'])->assertStatus(422);
    }
}
