<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingContract;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyRule;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Support\SoTienBangChu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Q - Hợp đồng du lịch và danh sách đoàn xuất tệp.
 *
 * Hội đồng hỏi thẳng: mua tour trọn gói thì hợp đồng đâu, danh sách khách gửi khách sạn đâu.
 *
 * Điều bộ test này giữ: **số hợp đồng cấp một lần rồi cố định**, và hợp đồng chỉ cấp cho đơn đã
 * thành giao dịch. Đơn đang giữ chỗ mà in ra hợp đồng là đưa khách một văn bản nói hai bên đã
 * thỏa thuận, trong khi chưa bên nào cam kết gì.
 */
class ContractTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private Tour $tour;
    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dieuHanh = User::create([
            'name' => 'Dieu hanh',
            'email' => Str::random(8) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $chinhSach = CancellationPolicy::create([
            'name' => 'Chính sách chuẩn',
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ]);

        CancellationPolicyRule::create([
            'cancellation_policy_id' => $chinhSach->id,
            'min_days_before' => 360,
            'max_days_before' => null,
            'refund_percent' => 80,
        ]);

        CancellationPolicyRule::create([
            'cancellation_policy_id' => $chinhSach->id,
            'min_days_before' => 0,
            'max_days_before' => 360,
            'refund_percent' => 30,
        ]);

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'adult_price' => 2_000_000,
            'child_price' => 1_400_000,
            'infant_price' => 0,
            'cancellation_policy_id' => $chinhSach->id,
        ]);

        $this->chuyen = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => 'confirmed',
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(12),
            'booking_deadline' => now()->addDays(7),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ]);
    }

    private function taoDon(string $status = 'confirmed'): Booking
    {
        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $this->chuyen->id,
            'customer_name' => 'Nguyen Van Khach',
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'customer_phone' => '0901234567',
            'departure_date' => $this->chuyen->start_date,
            'guests' => 3,
            'adult_count' => 2,
            'child_count' => 1,
            'infant_count' => 0,
            'total_amount' => 5_400_000,
            'status' => $status,
            'cancellation_policy_id' => $this->tour->cancellation_policy_id,
            'paid_at' => $status === 'confirmed' ? now() : null,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }

    // --- Cấp hợp đồng ----------------------------------------------------------------------

    public function test_cap_duoc_hop_dong_cho_don_da_xac_nhan(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/bookings/' . $don->id . '/contract')
            ->assertOk()
            ->assertJsonPath('data.contract_number', 'HD-' . now()->year . '-00001')
            ->assertJsonPath('data.booking_id', $don->id);

        $this->assertDatabaseHas('booking_contracts', [
            'booking_id' => $don->id,
            'issued_by' => $this->dieuHanh->id,
        ]);
    }

    /**
     * Bài quan trọng nhất của nhóm.
     *
     * Khách đang cầm bản in ghi số cũ. Cấp số thứ hai cho cùng một đơn là tạo ra hai hợp đồng cho
     * một giao dịch, và không ai biết bản nào có hiệu lực.
     */
    public function test_goi_lai_khong_sinh_so_hop_dong_moi(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->dieuHanh);

        $lan1 = $this->postJson('/api/admin/bookings/' . $don->id . '/contract')->json('data.contract_number');
        $lan2 = $this->postJson('/api/admin/bookings/' . $don->id . '/contract')->json('data.contract_number');

        $this->assertSame($lan1, $lan2);
        $this->assertSame(1, BookingContract::query()->count());
    }

    public function test_so_hop_dong_tang_dan_va_khong_trung(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $so = [];
        for ($i = 0; $i < 3; $i++) {
            $don = $this->taoDon();
            $so[] = $this->postJson('/api/admin/bookings/' . $don->id . '/contract')
                ->json('data.contract_number');
        }

        $nam = now()->year;

        // Năm chữ số theo đúng HD-YYYY-NNNNN mà tài liệu 05 mục 2.2 chốt.
        $this->assertSame(
            ['HD-' . $nam . '-00001', 'HD-' . $nam . '-00002', 'HD-' . $nam . '-00003'],
            $so,
        );
    }

    /** Đơn đang giữ chỗ chưa phải giao dịch: khách chưa trả tiền, chỗ có thể bị nhả bất cứ lúc nào. */
    public function test_don_dang_giu_cho_thi_chua_cap_hop_dong(): void
    {
        $don = $this->taoDon('pending');

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/bookings/' . $don->id . '/contract')->assertStatus(422);

        $this->assertSame(0, BookingContract::query()->count());
    }

    public function test_don_da_huy_thi_khong_cap_hop_dong(): void
    {
        $don = $this->taoDon('cancelled');

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/bookings/' . $don->id . '/contract')->assertStatus(422);
    }

    /** Chưa cấp thì trả về null, không phải 404: đó là câu trả lời bình thường cho màn hình. */
    public function test_don_chua_co_hop_dong_thi_tra_ve_null(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->dieuHanh);

        $this->getJson('/api/admin/bookings/' . $don->id . '/contract')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_ghi_nhan_duoc_hop_dong_da_ky(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->dieuHanh);

        $id = $this->postJson('/api/admin/bookings/' . $don->id . '/contract')->json('data.id');

        $this->putJson('/api/admin/contracts/' . $id . '/signed', [
            'note' => 'Khach ky truc tiep tai van phong.',
        ])->assertOk();

        $this->assertNotNull(BookingContract::query()->find($id)->signed_at);
    }

    // --- Bản in ----------------------------------------------------------------------------

    public function test_ban_in_hien_du_thong_tin_hop_dong(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->dieuHanh);

        $data = $this->postJson('/api/admin/bookings/' . $don->id . '/contract')->json('data');

        $this->get($data['print_url'])
            ->assertOk()
            ->assertSee($data['contract_number'])
            ->assertSee('Nguyen Van Khach')
            ->assertSee($this->tour->title)
            // Điều 6 là điều khoản bất khả kháng - phần trả lời câu hỏi của hội đồng về mưa bão.
            ->assertSee('bất khả kháng', false)
            // Bậc hoàn phải in ra theo chính sách đơn đã chép lúc đặt.
            ->assertSee('80%')
            ->assertSee('30%');
    }

    /**
     * Điều 4 phải nói được đã thu bao nhiêu, còn bao nhiêu, hạn khi nào.
     *
     * Tài liệu 05 mục 2.2 điểm 7 đòi ba con số ấy, và cả ba đều đọc từ đơn chứ không phải câu chữ
     * mẫu. Đơn dưới đây chưa thanh toán, nên hợp đồng phải ghi còn nợ đủ số.
     */
    public function test_dieu_4_ghi_dung_so_da_thu_va_con_phai_tra(): void
    {
        $don = $this->taoDon();
        $don->forceFill(['paid_at' => null])->save();

        Sanctum::actingAs($this->dieuHanh);

        $url = $this->postJson('/api/admin/bookings/' . $don->id . '/contract')->json('data.print_url');

        $this->get($url)
            ->assertOk()
            ->assertSee('Bên B đã thanh toán', false)
            ->assertSee('Còn phải thanh toán', false)
            ->assertSee('Hạn thanh toán phần còn lại', false)
            // Chưa trả đồng nào thì còn nợ đúng tổng giá trị.
            ->assertSee('5.400.000 đ');
    }

    public function test_don_da_tra_du_thi_dieu_4_khong_doi_them(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->dieuHanh);

        $url = $this->postJson('/api/admin/bookings/' . $don->id . '/contract')->json('data.print_url');

        $this->get($url)
            ->assertOk()
            ->assertSee('Bên B đã thanh toán đủ', false)
            ->assertDontSee('Hạn thanh toán phần còn lại', false);
    }

    /** Liên kết không có chữ ký thì không mở được: hợp đồng chứa thông tin cá nhân của khách. */
    public function test_khong_co_chu_ky_thi_khong_mo_duoc_ban_in(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->dieuHanh);

        $id = $this->postJson('/api/admin/bookings/' . $don->id . '/contract')->json('data.id');

        $this->get('/contracts/' . $id . '/print')->assertStatus(403);
    }

    // --- Số tiền bằng chữ ------------------------------------------------------------------

    /**
     * Bốn chỗ tiếng Việt hay đọc sai. Hợp đồng ghi sai số tiền bằng chữ là hợp đồng phải in lại.
     */
    public function test_doc_so_tien_bang_chu(): void
    {
        $bang = [
            0 => 'Không đồng',
            5 => 'Năm đồng',
            11 => 'Mười một đồng',
            15 => 'Mười lăm đồng',
            21 => 'Hai mươi mốt đồng',
            25 => 'Hai mươi lăm đồng',
            105 => 'Một trăm lẻ năm đồng',
            1_000 => 'Một nghìn đồng',
            1_005 => 'Một nghìn không trăm lẻ năm đồng',
            5_400_000 => 'Năm triệu bốn trăm nghìn đồng',
            10_000_000 => 'Mười triệu đồng',
            123_456_789 => 'Một trăm hai mươi ba triệu bốn trăm năm mươi sáu nghìn bảy trăm tám mươi chín đồng',
        ];

        foreach ($bang as $so => $mongDoi) {
            $this->assertSame($mongDoi, SoTienBangChu::doc($so), 'Đọc sai số ' . $so);
        }
    }

    // --- Xuất danh sách đoàn ---------------------------------------------------------------

    public function test_xuat_duoc_danh_sach_doan_ra_tep(): void
    {
        $don = $this->taoDon();

        $don->passengers()->create([
            'name' => 'Trần Thị Hành Khách',
            'type' => 'adult',
            'gender' => 'female',
            'identity_number' => '079123456789',
            'id_type' => 'cccd',
        ]);

        Sanctum::actingAs($this->dieuHanh);

        $phanHoi = $this->get('/api/admin/schedules/' . $this->chuyen->id . '/manifest/export');

        $phanHoi->assertOk();
        $phanHoi->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $noiDung = $phanHoi->streamedContent();

        // BOM UTF-8: thiếu ba byte này thì Excel trên Windows đọc hỏng hết dấu tiếng Việt.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $noiDung);

        $this->assertStringContainsString('Trần Thị Hành Khách', $noiDung);
        $this->assertStringContainsString('079123456789', $noiDung);
        $this->assertStringContainsString('Người lớn', $noiDung);
        // Dấu chấm phẩy, vì Excel bản tiếng Việt hiểu dấu phẩy là dấu thập phân.
        $this->assertStringContainsString(';', $noiDung);
    }

    /**
     * Nhóm chưa khai vẫn phải có mặt trong tệp.
     *
     * Bỏ qua thì tổng số khách trên tệp ít hơn số chỗ đã bán mà không ai giải thích được vì sao,
     * và người đọc tưởng đoàn ít người hơn thực tế.
     */
    public function test_nhom_chua_khai_van_xuat_hien_kem_ly_do(): void
    {
        $this->taoDon();

        Sanctum::actingAs($this->dieuHanh);

        $noiDung = $this->get('/api/admin/schedules/' . $this->chuyen->id . '/manifest/export')
            ->streamedContent();

        $this->assertStringContainsString('CHƯA KHAI', $noiDung);
        $this->assertStringContainsString('còn thiếu 3 khách', $noiDung);
    }
}
