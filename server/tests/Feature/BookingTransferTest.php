<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingTransfer;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\BookingTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * I08 - Chuyển đơn sang chuyến khác.
 *
 * Câu hỏi hội đồng nêu đầu tiên. Luật ở docs/nghiep-vu/02-luong-dat-tour.md mục 4.
 *
 * Phần dễ sai nhất là số chỗ ở hai đầu: trả ở chuyến gốc và lấy ở chuyến đích phải cùng thành
 * công hoặc cùng không, nếu không tổng số chỗ bán ra sai vĩnh viễn mà không ai phát hiện.
 */
class BookingTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private Tour $tour;
    private TourSchedule $chuyenGoc;
    private TourSchedule $chuyenDich;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dieuHanh = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'number_of_days' => 2,
            'adult_price' => 2_000_000,
            'child_price' => 1_400_000,
            'infant_price' => 0,
        ]);

        $this->chuyenGoc = $this->taoChuyen(now()->addDays(30));
        $this->chuyenDich = $this->taoChuyen(now()->addDays(45));
    }

    private function taoChuyen($start, ?Tour $tour = null, array $ghiDe = []): TourSchedule
    {
        $start = \Illuminate\Support\Carbon::parse($start);

        return TourSchedule::create(array_merge([
            'tour_id' => ($tour ?? $this->tour)->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 10,
            'min_people' => 2,
            'booked_people' => 0,
        ], $ghiDe));
    }

    private function taoDon(?TourSchedule $schedule = null, string $status = 'confirmed', int $nguoiLon = 2): Booking
    {
        $schedule ??= $this->chuyenGoc;
        $schedule->increment('booked_people', $nguoiLon);
        $schedule->refresh();

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $schedule->tour_id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Chuyen',
            'customer_email' => 'chuyen-' . Str::random(5) . '@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => $nguoiLon,
            'adult_count' => $nguoiLon,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => $nguoiLon * 2_000_000,
            'status' => $status,
            'paid_at' => $status === 'confirmed' ? now()->subDay() : null,
            'confirmed_at' => $status === 'confirmed' ? now()->subDay() : null,
        ]);
    }

    private function service(): BookingTransferService
    {
        return app(BookingTransferService::class);
    }

    // --- Số chỗ ở hai đầu ---------------------------------------------------------------

    /** Bài quan trọng nhất: chỗ phải rời chuyến gốc và tới chuyến đích, không mất không nhân đôi. */
    public function test_chuyen_thi_cho_roi_chuyen_goc_va_toi_chuyen_dich(): void
    {
        $don = $this->taoDon();

        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company');

        $this->assertSame(0, (int) $this->chuyenGoc->fresh()->booked_people);
        $this->assertSame(2, (int) $this->chuyenDich->fresh()->booked_people);
        $this->assertSame($this->chuyenDich->id, (int) $don->fresh()->tour_schedule_id);
    }

    public function test_sau_khi_chuyen_thi_so_cho_van_nhat_quan(): void
    {
        $don = $this->taoDon();

        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company');

        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }

    public function test_chuyen_lam_day_chuyen_dich_thi_dong_ban(): void
    {
        $chuyenNho = $this->taoChuyen(now()->addDays(50), null, ['max_people' => 2]);
        $don = $this->taoDon();

        $this->service()->transfer($don, $chuyenNho, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company');

        $this->assertSame(
            ScheduleStatus::Closed->value,
            $chuyenNho->fresh()->getRawOriginal('status'),
        );
    }

    /** Chuyến gốc vừa trống chỗ thì mở bán lại, nếu vẫn còn trong hạn chốt. */
    public function test_chuyen_goc_dang_day_thi_mo_ban_lai(): void
    {
        $don = $this->taoDon(nguoiLon: 10);
        $this->chuyenGoc->update(['status' => ScheduleStatus::Closed->value]);

        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company');

        $this->assertSame(
            ScheduleStatus::Open->value,
            $this->chuyenGoc->fresh()->getRawOriginal('status'),
        );
    }

    // --- Điều kiện chuyển ---------------------------------------------------------------

    public function test_chuyen_dich_khong_du_cho_thi_tu_choi(): void
    {
        $chuyenChat = $this->taoChuyen(now()->addDays(50), null, ['max_people' => 1]);
        $don = $this->taoDon();

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->service()->transfer($don, $chuyenChat, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company');
    }

    /** Từ chối thì không được để lại dấu vết nào ở số chỗ của cả hai chuyến. */
    public function test_tu_choi_thi_so_cho_hai_dau_giu_nguyen(): void
    {
        $chuyenChat = $this->taoChuyen(now()->addDays(50), null, ['max_people' => 1]);
        $don = $this->taoDon();

        try {
            $this->service()->transfer($don, $chuyenChat, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company');
        } catch (\App\Exceptions\BusinessRuleException) {
            // Bỏ qua, phần cần kiểm là số chỗ bên dưới.
        }

        $this->assertSame(2, (int) $this->chuyenGoc->fresh()->booked_people);
        $this->assertSame(0, (int) $chuyenChat->fresh()->booked_people);
        $this->assertSame($this->chuyenGoc->id, (int) $don->fresh()->tour_schedule_id);
    }

    public function test_don_chua_thanh_toan_thi_khong_chuyen(): void
    {
        $don = $this->taoDon(status: 'pending');

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company');
    }

    public function test_chuyen_goc_dang_chay_thi_khong_chuyen_duoc(): void
    {
        $don = $this->taoDon();
        $this->chuyenGoc->update([
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subHours(3),
            'end_date' => now()->addDay(),
        ]);

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company');
    }

    /**
     * Bài này bịt một lỗ từng có thật.
     *
     * Trước đây chuyển chuyến luôn trả chỗ về kho ở chuyến gốc, không hỏi hạn chốt - mâu thuẫn
     * trực tiếp với nhóm C, nơi cùng tình huống đó thì chỗ phải giữ lại thành ghế chết. Hệ quả
     * là chuyến gốc tưởng còn chỗ và bán cho người không có phòng, không có bảo hiểm.
     *
     * Về tiền còn tệ hơn hủy muộn: hủy thì công ty giữ lại phần lớn tiền theo bảng phí, còn
     * chuyển thì tiền đi theo khách sang chuyến mới trong khi suất cũ đã trả rồi.
     */
    public function test_qua_han_chot_thi_khong_chuyen_duoc_nua(): void
    {
        $don = $this->taoDon();
        $this->chuyenGoc->update(['booking_deadline' => now()->subHour()]);

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company');
    }

    /** Hãng khởi xướng được miễn hạn báo trước, nhưng không miễn được hạn chốt danh sách. */
    public function test_hang_khoi_xuong_cung_khong_lach_duoc_han_chot(): void
    {
        $don = $this->taoDon();
        $this->chuyenGoc->update(['booking_deadline' => now()->subHour()]);

        $duBao = $this->service()->preview($don, $this->chuyenDich, 'company');

        $this->assertFalse($duBao['can_transfer']);
        $this->assertStringContainsString('hạn chốt', $duBao['blocked_reason']);
    }

    /** Từ chối vì quá hạn chốt thì số chỗ hai đầu phải giữ nguyên. */
    public function test_tu_choi_vi_qua_han_chot_thi_so_cho_giu_nguyen(): void
    {
        $don = $this->taoDon();
        $this->chuyenGoc->update(['booking_deadline' => now()->subHour()]);

        try {
            $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company');
        } catch (\App\Exceptions\BusinessRuleException) {
            // Bỏ qua, phần cần kiểm nằm bên dưới.
        }

        $this->assertSame(2, (int) $this->chuyenGoc->fresh()->booked_people);
        $this->assertSame(0, (int) $this->chuyenDich->fresh()->booked_people);
    }

    /**
     * Chuyển và ghép là hai đường riêng, nhưng cùng dừng ở hạn chốt.
     *
     * Hai đường ghi cùng chạm số chỗ mà một đường có luật, một đường không, là mẫu lỗi đã lặp
     * lại nhiều lần trong dự án này. Bài này khóa cả hai lại cùng lúc.
     */
    public function test_ca_chuyen_lan_ghep_deu_dung_o_han_chot(): void
    {
        $chuyenKe = $this->taoChuyen(now()->addDays(31));

        $this->taoDon();
        $this->chuyenGoc->update(['booking_deadline' => now()->subHour()]);

        $duBaoChuyen = $this->service()->preview(
            Booking::query()->where('tour_schedule_id', $this->chuyenGoc->id)->first(),
            $this->chuyenDich,
            'company',
        );

        $duBaoGhep = app(\App\Services\ScheduleMergeService::class)
            ->preview($this->chuyenGoc->fresh(), $chuyenKe);

        $this->assertFalse($duBaoChuyen['can_transfer']);
        $this->assertFalse($duBaoGhep['can_merge']);
    }

    public function test_chuyen_dich_da_dong_ban_thi_tu_choi(): void
    {
        $don = $this->taoDon();
        $this->chuyenDich->update(['status' => ScheduleStatus::Closed->value]);

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company');
    }

    /**
     * Hai luật khác nhau, đừng lẫn:
     *
     * - Hạn báo trước 7 ngày là phép lịch sự với vận hành, hãng khởi xướng thì bỏ qua được.
     * - Hạn chốt danh sách là sự thật về tiền đã trả cho nhà cung cấp, không ai bỏ qua được.
     *
     * Chuyến ở mốc 5 ngày nằm giữa hai mốc đó: chưa qua hạn chốt (còn 2 ngày nữa), nhưng đã
     * quá hạn báo trước của khách.
     */
    public function test_khach_doi_sat_ngay_di_thi_tu_choi_nhung_hang_van_chuyen_duoc(): void
    {
        $chuyenGan = $this->taoChuyen(now()->addDays(5));
        $don = $this->taoDon($chuyenGan);

        $duBaoKhach = $this->service()->preview($don, $this->chuyenDich, 'customer');
        $this->assertFalse($duBaoKhach['can_transfer'], 'Khách đã quá hạn báo trước 7 ngày.');

        $duBaoHang = $this->service()->preview($don, $this->chuyenDich, 'company');
        $this->assertTrue($duBaoHang['can_transfer'], 'Hãng bỏ qua được hạn báo trước.');
    }

    // --- Chênh lệch giá và phí ----------------------------------------------------------

    public function test_chuyen_sang_tour_dat_hon_thi_tinh_lai_tong_tien(): void
    {
        $tourDat = Tour::factory()->create([
            'status' => 'active',
            'number_of_days' => 2,
            'adult_price' => 3_000_000,
            'child_price' => 2_100_000,
            'infant_price' => 0,
        ]);
        $chuyenDat = $this->taoChuyen(now()->addDays(50), $tourDat);
        $don = $this->taoDon();

        $banGhi = $this->service()->transfer($don, $chuyenDat, 'Khach doi sang tour khac.', $this->dieuHanh, 'company');

        $this->assertEquals(2_000_000, (float) $banGhi->price_difference);
        $this->assertEquals(6_000_000, (float) $don->fresh()->total_amount);
        $this->assertSame($tourDat->id, (int) $don->fresh()->tour_id);
    }

    public function test_lan_dau_mien_phi_lan_sau_thu_phi(): void
    {
        $don = $this->taoDon();

        $lanMot = $this->service()->transfer($don, $this->chuyenDich, 'Doi lan mot.', $this->dieuHanh, 'customer');
        $this->assertEquals(0, (float) $lanMot->fee);

        $chuyenBa = $this->taoChuyen(now()->addDays(60));
        $lanHai = $this->service()->transfer($don->fresh(), $chuyenBa, 'Doi lan hai.', $this->dieuHanh, 'customer');

        $this->assertGreaterThan(0, (float) $lanHai->fee);
        $this->assertSame(2, (int) $don->fresh()->transfer_count);
    }

    /** Hãng khởi xướng thì không bao giờ thu phí, kể cả lần thứ hai. */
    public function test_hang_khoi_xuong_thi_khong_thu_phi(): void
    {
        $don = $this->taoDon();
        $don->forceFill(['transfer_count' => 3])->save();

        $banGhi = $this->service()->transfer($don, $this->chuyenDich, 'Chuyen goc bi huy.', $this->dieuHanh, 'company');

        $this->assertEquals(0, (float) $banGhi->fee);
    }

    // --- Dấu vết ------------------------------------------------------------------------

    public function test_chuyen_chuyen_duoc_ghi_nhat_ky(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->dieuHanh);
        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company');

        $log = BookingAuditLog::query()->where('booking_id', $don->id)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('transferred', $log->action->value);
        $this->assertSame($this->chuyenGoc->id, (int) $log->old_values['tour_schedule_id']);
        $this->assertSame($this->chuyenDich->id, (int) $log->new_values['tour_schedule_id']);
    }

    public function test_moi_lan_chuyen_deu_luu_thanh_ban_ghi_rieng(): void
    {
        $don = $this->taoDon();
        $chuyenBa = $this->taoChuyen(now()->addDays(60));

        $this->service()->transfer($don, $this->chuyenDich, 'Doi lan mot.', $this->dieuHanh, 'company');
        $this->service()->transfer($don->fresh(), $chuyenBa, 'Doi lan hai.', $this->dieuHanh, 'company');

        $this->assertSame(2, BookingTransfer::query()->where('booking_id', $don->id)->count());
    }

    // --- API ----------------------------------------------------------------------------

    public function test_api_liet_ke_chuyen_co_the_chuyen_toi(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->dieuHanh);

        $response = $this->getJson("/api/admin/bookings/{$don->id}/transfer-options")
            ->assertOk();

        $ids = array_column($response->json('data.options'), 'schedule_id');

        $this->assertContains($this->chuyenDich->id, $ids);
        $this->assertNotContains($this->chuyenGoc->id, $ids, 'Không liệt kê chính chuyến hiện tại.');
    }

    public function test_api_chuyen_chuyen_thanh_cong(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->dieuHanh);

        $this->postJson("/api/admin/bookings/{$don->id}/transfer", [
            'to_schedule_id' => $this->chuyenDich->id,
            'reason' => 'Khach xin doi sang ngay khac vi ban viec.',
        ])->assertOk();

        $this->assertSame($this->chuyenDich->id, (int) $don->fresh()->tour_schedule_id);
    }

    public function test_api_bat_buoc_nhap_ly_do(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->dieuHanh);

        $this->postJson("/api/admin/bookings/{$don->id}/transfer", [
            'to_schedule_id' => $this->chuyenDich->id,
            'reason' => 'Ngan',
        ])->assertStatus(422);
    }

    public function test_khach_khong_goi_duoc_api_chuyen_chuyen(): void
    {
        $don = $this->taoDon();

        $khach = User::create([
            'name' => 'Khach',
            'email' => 'khach-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($khach);

        $this->postJson("/api/admin/bookings/{$don->id}/transfer", [
            'to_schedule_id' => $this->chuyenDich->id,
            'reason' => 'Toi muon tu doi chuyen.',
        ])->assertStatus(403);
    }
}
