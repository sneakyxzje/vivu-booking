<?php

namespace Tests\Feature;

use App\Enums\ContactChannel;
use App\Enums\ContactOutcome;
use App\Enums\ContactPurpose;
use App\Enums\ScheduleStatus;
use App\Enums\TransferReasonCategory;
use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingTransfer;
use App\Models\CustomerContactLog;
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

    /**
     * Một cuộc trao đổi đã ghi nhận, khách đồng ý — căn cứ để chuyển chuyến.
     *
     * Hầu hết bài dưới đây kiểm những chuyện khác (số chỗ, tiền, nhật ký) nên chỉ cần có căn cứ
     * hợp lệ là đủ. Bản thân luật "phải hỏi khách" có nhóm bài riêng ở cuối tệp.
     */
    private function daHoiKhach(Booking $don, ContactOutcome $ketQua = ContactOutcome::Agreed): CustomerContactLog
    {
        return CustomerContactLog::create([
            'booking_id' => $don->id,
            'channel' => ContactChannel::Phone->value,
            'purpose' => ContactPurpose::Transfer->value,
            'outcome' => $ketQua->value,
            'note' => 'Da goi cho khach, trao doi ve viec doi sang chuyen khac.',
            'contacted_by' => $this->dieuHanh->id,
            'contacted_at' => now(),
        ]);
    }

    // --- Số chỗ ở hai đầu ---------------------------------------------------------------

    /** Bài quan trọng nhất: chỗ phải rời chuyến gốc và tới chuyến đích, không mất không nhân đôi. */
    public function test_chuyen_thi_cho_roi_chuyen_goc_va_toi_chuyen_dich(): void
    {
        $don = $this->taoDon();

        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));

        $this->assertSame(0, (int) $this->chuyenGoc->fresh()->booked_people);
        $this->assertSame(2, (int) $this->chuyenDich->fresh()->booked_people);
        $this->assertSame($this->chuyenDich->id, (int) $don->fresh()->tour_schedule_id);
    }

    public function test_sau_khi_chuyen_thi_so_cho_van_nhat_quan(): void
    {
        $don = $this->taoDon();

        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));

        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }

    public function test_chuyen_lam_day_chuyen_dich_thi_dong_ban(): void
    {
        $chuyenNho = $this->taoChuyen(now()->addDays(50), null, ['max_people' => 2]);
        $don = $this->taoDon();

        $this->service()->transfer($don, $chuyenNho, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));

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

        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));

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

        $this->service()->transfer($don, $chuyenChat, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));
    }

    /** Từ chối thì không được để lại dấu vết nào ở số chỗ của cả hai chuyến. */
    public function test_tu_choi_thi_so_cho_hai_dau_giu_nguyen(): void
    {
        $chuyenChat = $this->taoChuyen(now()->addDays(50), null, ['max_people' => 1]);
        $don = $this->taoDon();

        try {
            $this->service()->transfer($don, $chuyenChat, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));
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

        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));
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

        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));
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

        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));
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
            $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));
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

    /**
     * Hạn chốt phải chặn ở CẢ HAI đầu, không riêng chuyến gốc.
     *
     * Chuyến đích quá hạn mà vẫn còn `open` là chuyện thường: lệnh nền đóng chuyến chạy theo lịch,
     * còn điều hành rút ngắn hạn chốt thì có hiệu lực ngay. Trong khoảng giữa hai mốc ấy, nhận
     * thêm một đơn làm số chỗ đã bán vượt số suất đã chốt với nhà cung cấp - khách có vé mà không
     * có phòng, và không ai thấy sai ở đâu vì cả hai màn hình đều báo thành công.
     */
    public function test_chuyen_dich_qua_han_chot_thi_khong_nhan_them_khach(): void
    {
        $don = $this->taoDon();
        $this->chuyenDich->update(['booking_deadline' => now()->subHour()]);

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->service()->transfer($don, $this->chuyenDich->fresh(), 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));
    }

    /** Hãng khởi xướng được miễn hạn báo trước, nhưng hạn chốt của chuyến đích thì không ai miễn. */
    public function test_hang_khoi_xuong_cung_khong_lach_duoc_han_chot_chuyen_dich(): void
    {
        $don = $this->taoDon();
        $this->chuyenDich->update(['booking_deadline' => now()->subHour()]);

        $duBao = $this->service()->preview($don, $this->chuyenDich->fresh(), 'company');

        $this->assertFalse($duBao['can_transfer']);
        $this->assertStringContainsString('hạn chốt', $duBao['blocked_reason']);

        // Và số chỗ hai đầu không được nhúc nhích.
        $this->assertSame(2, (int) $this->chuyenGoc->fresh()->booked_people);
        $this->assertSame(0, (int) $this->chuyenDich->fresh()->booked_people);
    }

    public function test_chuyen_dich_da_dong_ban_thi_tu_choi(): void
    {
        $don = $this->taoDon();
        $this->chuyenDich->update(['status' => ScheduleStatus::Closed->value]);

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));
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

        $banGhi = $this->service()->transfer($don, $chuyenDat, 'Khach doi sang tour khac.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));

        $this->assertEquals(2_000_000, (float) $banGhi->price_difference);
        $this->assertEquals(6_000_000, (float) $don->fresh()->total_amount);
        $this->assertSame($tourDat->id, (int) $don->fresh()->tour_id);
    }

    public function test_lan_dau_mien_phi_lan_sau_thu_phi(): void
    {
        $don = $this->taoDon();

        $lanMot = $this->service()->transfer($don, $this->chuyenDich, 'Doi lan mot.', $this->dieuHanh, 'customer', canCu: $this->daHoiKhach($don));
        $this->assertEquals(0, (float) $lanMot->fee);

        $chuyenBa = $this->taoChuyen(now()->addDays(60));
        $lanHai = $this->service()->transfer($don->fresh(), $chuyenBa, 'Doi lan hai.', $this->dieuHanh, 'customer', canCu: $this->daHoiKhach($don));

        $this->assertGreaterThan(0, (float) $lanHai->fee);
        $this->assertSame(2, (int) $don->fresh()->transfer_count);
    }

    /** Hãng khởi xướng thì không bao giờ thu phí, kể cả lần thứ hai. */
    public function test_hang_khoi_xuong_thi_khong_thu_phi(): void
    {
        $don = $this->taoDon();
        $don->forceFill(['transfer_count' => 3])->save();

        $banGhi = $this->service()->transfer($don, $this->chuyenDich, 'Chuyen goc bi huy.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));

        $this->assertEquals(0, (float) $banGhi->fee);
    }

    // --- Dấu vết ------------------------------------------------------------------------

    public function test_chuyen_chuyen_duoc_ghi_nhat_ky(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->dieuHanh);
        $this->service()->transfer($don, $this->chuyenDich, 'Khach xin doi sang ngay khac.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));

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

        $this->service()->transfer($don, $this->chuyenDich, 'Doi lan mot.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));
        $this->service()->transfer($don->fresh(), $chuyenBa, 'Doi lan hai.', $this->dieuHanh, 'company', canCu: $this->daHoiKhach($don));

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

    /**
     * Chuyến không chuyển được vẫn phải nằm trong danh sách, kèm lý do.
     *
     * Lọc sạch chúng đi thì lúc luật chặn thuộc về ĐƠN - quá hạn chốt ở chuyến gốc, hay khách xin
     * đổi khi còn dưới bảy ngày - cả danh sách trống trơn, và màn hình kết luận sai rằng không
     * chuyến nào còn chỗ, trong khi chuyến đích đang trống trơn.
     */
    public function test_api_van_liet_ke_chuyen_bi_chan_kem_ly_do(): void
    {
        $don = $this->taoDon();
        $this->chuyenGoc->update(['booking_deadline' => now()->subHour()]);

        Sanctum::actingAs($this->dieuHanh);

        $options = $this->getJson("/api/admin/bookings/{$don->id}/transfer-options")
            ->assertOk()
            ->json('data.options');

        $dich = collect($options)->firstWhere('schedule_id', $this->chuyenDich->id);

        $this->assertNotNull($dich, 'Chuyến bị chặn vẫn phải hiện ra.');
        $this->assertFalse($dich['can_transfer']);
        $this->assertStringContainsString('hạn chốt', $dich['blocked_reason']);
        $this->assertGreaterThan(0, $dich['remaining_seats'], 'Chỗ vẫn còn — lý do chặn không phải vì hết chỗ.');
    }

    /** Chuyến chuyển được xếp lên trước, để mắt không phải lọc qua danh sách mờ. */
    public function test_api_xep_chuyen_chuyen_duoc_len_truoc(): void
    {
        $don = $this->taoDon();

        // Chuyến này khởi hành sớm hơn chuyenDich nhưng đã đóng bán... nên dùng một chuyến quá hạn
        // chốt: vẫn mở bán, vẫn trong danh sách, nhưng bị chặn.
        $quaHan = $this->taoChuyen(now()->addDays(35), ghiDe: ['booking_deadline' => now()->subDay()]);

        Sanctum::actingAs($this->dieuHanh);

        $options = $this->getJson("/api/admin/bookings/{$don->id}/transfer-options")
            ->assertOk()
            ->json('data.options');

        $this->assertTrue($options[0]['can_transfer'], 'Dòng đầu phải là chuyến chuyển được.');
        $this->assertSame($quaHan->id, $options[count($options) - 1]['schedule_id']);
    }

    public function test_api_chuyen_chuyen_thanh_cong(): void
    {
        $don = $this->taoDon();
        $canCu = $this->daHoiKhach($don);
        Sanctum::actingAs($this->dieuHanh);

        $this->postJson("/api/admin/bookings/{$don->id}/transfer", [
            'to_schedule_id' => $this->chuyenDich->id,
            'contact_log_id' => $canCu->id,
            'reason_category' => TransferReasonCategory::CustomerRequest->value,
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

    // --- Phải hỏi khách trước --------------------------------------------------------------

    /**
     * Bài trung tâm của nhóm này: chưa ghi nhận cuộc trao đổi nào thì không chuyển được.
     *
     * Chuyển chuyến đổi ngày đi của người khác - họ đã xin nghỉ phép, đã đặt vé tàu tới điểm tập
     * kết. Điều hành thấy chuyến ngày 12 trống chỗ nên dời sang: hợp lý với bảng xếp chuyến, vô lý
     * với người phải đi.
     */
    public function test_chua_hoi_khach_thi_khong_chuyen_duoc(): void
    {
        $don = $this->taoDon();

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->service()->transfer($don, $this->chuyenDich, 'Doi cho gon lich.', $this->dieuHanh, 'company');
    }

    /** Chặn ở tầng dịch vụ thì mọi lối vào đều dính, kể cả lối gọi thẳng không qua API. */
    public function test_chua_hoi_khach_thi_so_cho_hai_dau_giu_nguyen(): void
    {
        $don = $this->taoDon();

        try {
            $this->service()->transfer($don, $this->chuyenDich, 'Doi cho gon lich.', $this->dieuHanh, 'company');
        } catch (\App\Exceptions\BusinessRuleException) {
            // Đúng như mong đợi.
        }

        $this->assertSame(2, (int) $this->chuyenGoc->fresh()->booked_people);
        $this->assertSame(0, (int) $this->chuyenDich->fresh()->booked_people);
    }

    /**
     * Khách không đồng ý, hoặc không liên lạc được, thì bản ghi ấy không phải giấy phép.
     *
     * Ghi nhận được cả hai kết quả xấu là chủ ý - nhật ký chỉ có giá trị khi nó ghi cả những lần
     * không thành. Nhưng ghi nhận không đồng nghĩa với cho phép.
     */
    public function test_khach_khong_dong_y_thi_khong_dung_lam_can_cu_duoc(): void
    {
        foreach ([ContactOutcome::Refused, ContactOutcome::Unreachable] as $ketQua) {
            $don = $this->taoDon();
            $canCu = $this->daHoiKhach($don, $ketQua);

            try {
                $this->service()->transfer(
                    $don,
                    $this->chuyenDich,
                    'Doi cho gon lich.',
                    $this->dieuHanh,
                    'company',
                    canCu: $canCu,
                );

                $this->fail(sprintf('Kết quả "%s" lẽ ra phải bị từ chối.', $ketQua->value));
            } catch (\App\Exceptions\BusinessRuleException $e) {
                $this->assertStringContainsString($ketQua->label(), $e->getMessage());
            }
        }
    }

    /**
     * Một cái gật đầu là gật cho một phương án, không phải giấy phép dùng mãi.
     *
     * Không có luật này thì lần chuyển thứ hai - sang một chuyến khác hẳn, vì một lý do khác hẳn -
     * vẫn mượn được cuộc gọi của lần trước.
     */
    public function test_mot_can_cu_chi_dung_duoc_cho_mot_lan_chuyen(): void
    {
        $chuyenBa = $this->taoChuyen(now()->addDays(60));
        $don = $this->taoDon();
        $canCu = $this->daHoiKhach($don);

        $this->service()->transfer($don, $this->chuyenDich, 'Doi lan mot.', $this->dieuHanh, 'company', canCu: $canCu);

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->service()->transfer($don->fresh(), $chuyenBa, 'Doi lan hai.', $this->dieuHanh, 'company', canCu: $canCu);
    }

    /** Bản ghi của đơn khác cũng không dùng được, dù nội dung có đẹp đến đâu. */
    public function test_can_cu_cua_don_khac_thi_khong_dung_duoc(): void
    {
        $don = $this->taoDon();
        $donKhac = $this->taoDon();

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->service()->transfer(
            $don,
            $this->chuyenDich,
            'Doi cho gon lich.',
            $this->dieuHanh,
            'company',
            canCu: $this->daHoiKhach($donKhac),
        );
    }

    /**
     * Ngoại lệ duy nhất: cả chuyến gốc bị hủy.
     *
     * Ở đó không có phương án nào để hỏi ý - chuyến của khách không còn tồn tại. Luồng hủy chuyến
     * gửi thư báo kèm lựa chọn hoàn tiền, và khách trả lời bằng cách nhận tiền hoặc đi chuyến mới.
     * Cờ `nguonBiHuy` đã có sẵn từ trước cho luật hạn chốt, không phải cửa sau mở thêm.
     */
    public function test_chuyen_goc_bi_huy_thi_khong_can_can_cu(): void
    {
        $don = $this->taoDon();

        $banGhi = $this->service()->transfer(
            $don,
            $this->chuyenDich,
            'Cong ty huy chuyen goc.',
            $this->dieuHanh,
            'company',
            nguonBiHuy: true,
        );

        $this->assertNull($banGhi->contact_log_id);
        $this->assertSame($this->chuyenDich->id, (int) $don->fresh()->tour_schedule_id);
    }

    // --- Nhóm lý do -------------------------------------------------------------------------

    /**
     * Chuyển vì bão thì không thu phí đổi lịch, dù đơn đã đổi một lần rồi.
     *
     * Thu phí của người phải dời chuyến vì bão là bắt họ trả cho việc không ai gây ra. Chỉ nhóm
     * "khách xin đổi vì việc riêng" mới chịu quy tắc phí.
     */
    public function test_ly_do_bat_kha_khang_thi_khong_thu_phi(): void
    {
        $don = $this->taoDon();
        $don->forceFill(['transfer_count' => 1])->save();

        $this->assertSame(
            0.0,
            $this->service()->transferFee($don, 'customer', TransferReasonCategory::ForceMajeure),
        );

        $this->assertGreaterThan(
            0.0,
            $this->service()->transferFee($don, 'customer', TransferReasonCategory::CustomerRequest),
            'Khách xin đổi vì việc riêng, lần thứ hai, thì vẫn thu như cũ.',
        );
    }

    /** Nhóm lý do được lưu lại, để sau này còn biết chuyến ấy dời vì bão hay vì khách bận. */
    public function test_nhom_ly_do_duoc_luu_vao_ban_ghi(): void
    {
        $don = $this->taoDon();

        $banGhi = $this->service()->transfer(
            $don,
            $this->chuyenDich,
            'Bao so 9, cam bien, khong chay tau ra dao.',
            $this->dieuHanh,
            'company',
            canCu: $this->daHoiKhach($don),
            nhomLyDo: TransferReasonCategory::ForceMajeure,
        );

        $this->assertSame(TransferReasonCategory::ForceMajeure, $banGhi->fresh()->reason_category);
    }
}
