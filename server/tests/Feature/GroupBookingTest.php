<?php

namespace Tests\Feature;

use App\Enums\GroupRequestStatus;
use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\GroupBookingRequest;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\BookingHoldService;
use App\Services\CancellationPolicyService;
use App\Services\GroupBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Booking theo đoàn - điểm 14.
 *
 * Những điều bộ test này giữ:
 *
 *   1. **Chỉ bước chốt mới chiếm chỗ.** Yêu cầu và báo giá là thương lượng, chưa đụng vào kho.
 *   2. **Chốt đi qua đúng luật của đơn lẻ**: khóa chuyến, kiểm chỗ, kiểm hạn chốt. Đoàn to không
 *      phải lý do được vượt chỗ.
 *   3. **Sổ giao dịch là nguồn sự thật về tiền của đơn đoàn**: cọc rồi hủy sát ngày thì tiền hoàn
 *      trừ trên số cọc đã thu - phép "mất cọc" phải chạy đúng.
 *   4. **Giảm số khách chỉ dành cho đoàn, chỉ trước hạn chốt.** Đơn lẻ muốn đổi số người vẫn phải
 *      hủy đặt lại như quyết định đã chốt trước đây.
 */
class GroupBookingTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;
    private TourSchedule $chuyen;
    private User $admin;
    private GroupBookingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 3,
            'adult_price' => 2_000_000,
        ]);

        $this->chuyen = $this->taoChuyen(now()->addDays(30));
        $this->admin = $this->taoNguoi('admin');
        $this->service = app(GroupBookingService::class);
    }

    private function taoNguoi(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' ' . Str::random(4),
            'email' => Str::random(8) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function taoChuyen($start, int $maxPeople = 50): TourSchedule
    {
        $start = Carbon::parse($start);

        return TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => $maxPeople,
            'min_people' => 4,
            'booked_people' => 0,
        ]);
    }

    private function guiYeuCau(?TourSchedule $chuyen = null): GroupBookingRequest
    {
        return $this->service->submit([
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => ($chuyen ?? $this->chuyen)->id,
            'contact_name' => 'Trần Văn Đại Diện',
            'contact_email' => 'daidien@congtyx.vn',
            'contact_phone' => '0901112233',
            'estimated_guests' => 40,
            'company_name' => 'Công ty X',
            'tax_code' => '0312345678',
        ]);
    }

    private function baoGia(GroupBookingRequest $yc, float $gia = 1_800_000, int $mienPhi = 1): GroupBookingRequest
    {
        return $this->service->quote($yc, $gia, $mienPhi, now()->addDays(7), null, $this->admin);
    }

    // --- Yêu cầu và báo giá: thương lượng, chưa chiếm chỗ ------------------------------------

    public function test_gui_yeu_cau_khong_chiem_cho(): void
    {
        $yc = $this->guiYeuCau();

        $this->assertSame(GroupRequestStatus::PendingQuote, $yc->status);
        $this->assertNotEmpty($yc->public_token);
        $this->assertSame(0, (int) $this->chuyen->fresh()->booked_people, 'Yêu cầu chưa phải cam kết, không được trừ kho chỗ.');
    }

    public function test_khong_nhan_yeu_cau_vao_chuyen_da_dong_ban(): void
    {
        $dongBan = $this->taoChuyen(now()->addDays(30));
        $dongBan->update(['status' => ScheduleStatus::Closed->value]);

        $this->expectException(BusinessRuleException::class);

        $this->guiYeuCau($dongBan);
    }

    public function test_bao_gia_lai_de_len_bao_gia_cu(): void
    {
        $yc = $this->baoGia($this->guiYeuCau());
        $this->assertSame(GroupRequestStatus::Quoted, $yc->status);

        // Thương lượng: khách kỳ kèo, điều hành hạ giá.
        $this->service->quote($yc, 1_700_000, 2, now()->addDays(5), 'Chốt nhanh trong tuần', $this->admin);

        $moi = $yc->fresh();
        $this->assertSame(1_700_000.0, (float) $moi->quoted_price_per_person);
        $this->assertSame(2, $moi->quoted_free_slots);
    }

    // --- Chốt: bước duy nhất chạm kho chỗ ------------------------------------------------------

    public function test_chot_tao_don_that_va_chiem_cho(): void
    {
        $yc = $this->baoGia($this->guiYeuCau(), 1_800_000, 2);

        $booking = $this->service->confirm($yc, 42, $this->admin);

        $this->assertSame('confirmed', $booking->status);
        $this->assertSame(42, (int) $booking->guests);
        // 42 người trừ 2 suất miễn phí: miễn phí là chuyện của tiền, không phải của chỗ.
        $this->assertSame(40 * 1_800_000.0, round((float) $booking->total_amount));
        $this->assertSame(42, (int) $this->chuyen->fresh()->booked_people);
        $this->assertSame(GroupRequestStatus::Confirmed, $yc->fresh()->status);
        $this->assertTrue($booking->isGroup());
    }

    public function test_chot_bi_chan_khi_thieu_cho(): void
    {
        $nho = $this->taoChuyen(now()->addDays(30), 20);
        $yc = $this->baoGia($this->guiYeuCau($nho));

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/còn 20 chỗ trống/u');

        $this->service->confirm($yc, 25, $this->admin);
    }

    public function test_chot_bi_chan_khi_bao_gia_het_han(): void
    {
        $yc = $this->guiYeuCau();
        $this->service->quote($yc, 1_800_000, 0, now()->addHour(), null, $this->admin);

        $this->travel(2)->hours();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/hết hiệu lực/u');

        $this->service->confirm($yc->fresh(), 40, $this->admin);
    }

    public function test_chot_bi_chan_sau_han_chot_danh_sach(): void
    {
        $sapKhoiHanh = $this->taoChuyen(now()->addDays(10));
        $yc = $this->baoGia($this->guiYeuCau($sapKhoiHanh));

        // Quá hạn chốt danh sách nhưng báo giá vẫn còn hiệu lực - hạn chốt phải thắng.
        $sapKhoiHanh->update(['booking_deadline' => now()->subHour()]);

        $this->expectException(BusinessRuleException::class);

        $this->service->confirm($yc, 40, $this->admin);
    }

    public function test_don_doan_khong_bi_tac_vu_nha_cho_quet(): void
    {
        $yc = $this->baoGia($this->guiYeuCau());
        $booking = $this->service->confirm($yc, 40, $this->admin);

        // Đơn đoàn chưa thu đồng nào - đúng lúc dễ bị nhầm với đơn lẻ quá hạn thanh toán.
        $this->assertFalse($booking->fresh()->isOverdue());
        $this->assertSame(0, app(BookingHoldService::class)->releaseOverdueForSchedule($this->chuyen->id));
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_hai_lan_chot_cung_yeu_cau_chi_ra_mot_don(): void
    {
        $yc = $this->baoGia($this->guiYeuCau());

        $this->service->confirm($yc, 40, $this->admin);

        try {
            $this->service->confirm($yc->fresh(), 40, $this->admin);
            $this->fail('Lần chốt thứ hai phải bị từ chối.');
        } catch (BusinessRuleException) {
        }

        $this->assertSame(1, Booking::query()->where('group_booking_request_id', $yc->id)->count());
        $this->assertSame(40, (int) $this->chuyen->fresh()->booked_people, 'Chỗ không được trừ hai lần.');
    }

    // --- Sổ giao dịch: tiền về nhiều đợt --------------------------------------------------------

    public function test_thu_du_qua_nhieu_dot_thi_dong_moc_da_thanh_toan(): void
    {
        $yc = $this->baoGia($this->guiYeuCau(), 1_000_000, 0);
        $booking = $this->service->confirm($yc, 40, $this->admin); // tổng 40 triệu

        $this->service->recordPayment($booking, 'deposit', 12_000_000, 'bank_transfer', null, 'Cọc 30%', $this->admin);
        $this->assertNull($booking->fresh()->paid_at, 'Mới cọc chưa phải là đã thanh toán đủ.');

        $this->service->recordPayment($booking, 'balance', 28_000_000, 'bank_transfer', null, null, $this->admin);
        $this->assertNotNull($booking->fresh()->paid_at);
        $this->assertSame(40_000_000.0, $this->service->netPaid($booking));
    }

    public function test_khong_hoan_qua_so_da_thu(): void
    {
        $yc = $this->baoGia($this->guiYeuCau(), 1_000_000, 0);
        $booking = $this->service->confirm($yc, 40, $this->admin);

        $this->service->recordPayment($booking, 'deposit', 12_000_000, 'bank_transfer', null, null, $this->admin);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/Không hoàn quá số đã thu/u');

        $this->service->recordPayment($booking, 'refund', 15_000_000, 'bank_transfer', null, null, $this->admin);
    }

    /**
     * Đơn lẻ GIỜ CŨNG ghi sổ được — luật cũ đã gỡ.
     *
     * Câu chặn trước đây có lý do đúng vào lúc nó được viết: đơn lẻ trả một lần qua cổng, mở sổ
     * cho nó là hai nguồn sự thật về cùng một khoản tiền. Lý do ấy hết hiệu lực từ khi đơn lẻ
     * cũng trả cọc trước rồi trả nốt sau, và có thể trả bằng tiền mặt tại văn phòng — lúc đó
     * "đơn này đã thu bao nhiêu" không còn trả lời được bằng một cột `paid_at`.
     */
    public function test_don_le_cung_ghi_so_duoc(): void
    {
        $donLe = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $this->chuyen->id,
            'customer_name' => 'Khách Lẻ',
            'customer_email' => 'le@example.com',
            'departure_date' => $this->chuyen->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'total_amount' => 4_000_000,
            'status' => 'confirmed',
        ]);

        $this->service->recordPayment($donLe, 'deposit', 1_000_000, 'cash', null, 'Cọc tại quầy', $this->admin);

        $this->assertSame(1_000_000.0, $this->service->netPaid($donLe));
        $this->assertNull($donLe->fresh()->paid_at, 'Mới cọc thì chưa phải đã thu đủ.');

        $this->service->recordPayment($donLe, 'balance', 3_000_000, 'bank_transfer', null, null, $this->admin);

        $this->assertNotNull($donLe->fresh()->paid_at);
    }

    /**
     * Phép "mất cọc": đoàn cọc 30% rồi hủy sát ngày.
     *
     * Phí hủy tính trên GIÁ TRỊ ĐƠN, tiền hoàn trừ trên SỐ ĐÃ THU. Sát ngày phí hủy vượt xa tiền
     * cọc, kẹp dưới bằng 0: khách mất cọc nhưng không phải nộp thêm. Trước khi có sổ giao dịch,
     * paidAmount đọc theo paid_at kiểu có/không nên không mô tả nổi tình huống này.
     */
    public function test_phi_huy_tinh_tren_so_coc_da_thu_qua_so_giao_dich(): void
    {
        $yc = $this->baoGia($this->guiYeuCau(), 1_000_000, 0);
        $booking = $this->service->confirm($yc, 40, $this->admin); // tổng 40 triệu

        $this->service->recordPayment($booking, 'deposit', 12_000_000, 'bank_transfer', null, null, $this->admin);

        // Còn 24 giờ tới khởi hành: bậc dưới 48h, hoàn 0%.
        $quote = app(CancellationPolicyService::class)->quote(
            $booking->fresh(),
            $this->chuyen,
            null,
            Carbon::parse($this->chuyen->start_date)->subHours(24),
        );

        $this->assertSame(12_000_000.0, $quote['paid_amount'], 'Số đã thu phải đọc từ sổ, không phải từ tổng đơn.');
        $this->assertSame(40_000_000.0, $quote['cancellation_fee']);
        $this->assertSame(0.0, $quote['refund_amount'], 'Mất cọc, nhưng không bao giờ phải nộp thêm.');
    }

    // --- Giảm số khách: đặc quyền của đoàn ------------------------------------------------------

    public function test_doan_giam_so_khach_tra_cho_va_tinh_lai_tien(): void
    {
        $yc = $this->baoGia($this->guiYeuCau(), 1_000_000, 2);
        $booking = $this->service->confirm($yc, 42, $this->admin); // 40 người trả tiền

        $this->service->reduceGuests($booking, 37, $this->admin, '5 người bận việc đột xuất');

        $moi = $booking->fresh();
        $this->assertSame(37, (int) $moi->guests);
        $this->assertSame(35 * 1_000_000.0, round((float) $moi->total_amount));
        $this->assertSame(37, (int) $this->chuyen->fresh()->booked_people, 'Chỗ dư phải về kho vì còn trước hạn chốt.');
    }

    public function test_don_le_khong_duoc_giam_so_khach(): void
    {
        $donLe = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $this->chuyen->id,
            'customer_name' => 'Khách Lẻ',
            'customer_email' => 'le@example.com',
            'departure_date' => $this->chuyen->start_date,
            'guests' => 4,
            'adult_count' => 4,
            'total_amount' => 8_000_000,
            'status' => 'confirmed',
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/hủy và đặt lại/u');

        $this->service->reduceGuests($donLe, 3, $this->admin);
    }

    public function test_qua_han_chot_thi_khong_giam_duoc_nua(): void
    {
        $yc = $this->baoGia($this->guiYeuCau(), 1_000_000, 0);
        $booking = $this->service->confirm($yc, 40, $this->admin);

        $this->chuyen->update(['booking_deadline' => now()->subHour()]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/hạn chốt danh sách/u');

        $this->service->reduceGuests($booking, 35, $this->admin);
    }

    // --- Phía khách ----------------------------------------------------------------------------

    public function test_khach_tra_cuu_bang_ma_va_rut_yeu_cau(): void
    {
        $yc = $this->baoGia($this->guiYeuCau());

        $this->getJson('/api/group-bookings/' . $yc->public_token)
            ->assertOk()
            ->assertJsonPath('data.status', 'quoted')
            ->assertJsonPath('data.quote.free_slots', 1);

        $this->putJson('/api/group-bookings/' . $yc->public_token . '/withdraw')->assertOk();

        $this->assertSame(GroupRequestStatus::Withdrawn, $yc->fresh()->status);
    }

    public function test_da_chot_thi_khong_rut_duoc_nua(): void
    {
        $yc = $this->baoGia($this->guiYeuCau());
        $this->service->confirm($yc, 40, $this->admin);

        $response = $this->putJson('/api/group-bookings/' . $yc->fresh()->public_token . '/withdraw')
            ->assertStatus(422);

        // Câu từ chối phải chỉ sang luồng đúng: hủy đơn theo chính sách, không phải ngõ cụt.
        $this->assertStringContainsString('chính sách hủy', $response->json('message'));
    }

    public function test_dieu_hanh_ghi_so_qua_api(): void
    {
        $yc = $this->baoGia($this->guiYeuCau(), 1_000_000, 0);
        $booking = $this->service->confirm($yc, 40, $this->admin);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/bookings/' . $booking->id . '/payments', [
            'kind' => 'deposit',
            'amount' => 12_000_000,
            'method' => 'bank_transfer',
            'note' => 'Cọc 30% theo hợp đồng',
        ])->assertOk()->assertJsonPath('data.net_paid', 12_000_000);

        $this->getJson('/api/admin/bookings/' . $booking->id . '/payments')
            ->assertOk()
            ->assertJsonPath('data.total_amount', 40_000_000)
            ->assertJsonPath('data.paid_in_full', false)
            ->assertJsonCount(1, 'data.entries');
    }
}
