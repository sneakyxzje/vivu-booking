<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Mail\BookingCancelledMail;
use App\Mail\ScheduleMergedMail;
use App\Models\Booking;
use App\Models\BookingTransfer;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\ScheduleMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * L05 - Ghép hai chuyến của cùng một tour.
 *
 * Câu số 16 của hội đồng. Luật ở docs/nghiep-vu/04-luong-dieu-hanh.md mục 2.1.
 *
 * Tình huống: hai chuyến gần ngày nhau, mỗi chuyến bốn khách, không chuyến nào đủ mức tối thiểu.
 * Ghép thì cả hai đoàn được đi thay vì cả hai cùng bị hủy.
 */
class ScheduleMergeTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private Tour $tour;
    private TourSchedule $nguon;
    private TourSchedule $dich;

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
            'type' => TourType::Shared->value,
            'number_of_days' => 2,
            'adult_price' => 2_000_000,
        ]);

        $this->nguon = $this->taoChuyen(now()->addDays(20));
        $this->dich = $this->taoChuyen(now()->addDays(21));
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
            'max_people' => 20,
            'min_people' => 10,
            'booked_people' => 0,
        ], $ghiDe));
    }

    private function taoDon(TourSchedule $schedule, string $status = 'confirmed', int $khach = 2): Booking
    {
        $schedule->increment('booked_people', $khach);
        $schedule->refresh();

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $schedule->tour_id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach ' . Str::random(4),
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => $khach,
            'adult_count' => $khach,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => $khach * 2_000_000,
            'status' => $status,
            'paid_at' => $status === 'confirmed' ? now()->subDay() : null,
            'confirmed_at' => $status === 'confirmed' ? now()->subDay() : null,
            'expires_at' => $status === 'pending' ? now()->addDay() : null,
        ]);
    }

    private function service(): ScheduleMergeService
    {
        return app(ScheduleMergeService::class);
    }

    // --- Luồng chính --------------------------------------------------------------------

    public function test_ghep_thi_don_da_thanh_toan_chuyen_sang_chuyen_dich(): void
    {
        $donMot = $this->taoDon($this->nguon);
        $donHai = $this->taoDon($this->nguon);
        $this->taoDon($this->dich);

        $ketQua = $this->service()->merge($this->nguon, $this->dich, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);

        $this->assertSame(2, $ketQua['transferred']);
        $this->assertSame($this->dich->id, (int) $donMot->fresh()->tour_schedule_id);
        $this->assertSame($this->dich->id, (int) $donHai->fresh()->tour_schedule_id);
    }

    // --- Nói cho khách biết -------------------------------------------------------------

    /**
     * Ghép chuyến đổi ngày đi của người đã trả tiền mà không hỏi họ.
     *
     * Đó là quyết định vận hành, chấp nhận được. Không báo lại mới là chỗ không chấp nhận được:
     * khách biết chuyện khi ra bến vào đúng ngày cũ.
     */
    public function test_ghep_thi_gui_thu_cho_ca_hai_nhom_khach(): void
    {
        Mail::fake();

        $daTra = $this->taoDon($this->nguon);
        $chuaTra = $this->taoDon($this->nguon, 'pending');

        $this->service()->merge($this->nguon, $this->dich, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);

        Mail::assertQueued(
            ScheduleMergedMail::class,
            fn (ScheduleMergedMail $thu) => $thu->hasTo($daTra->customer_email),
        );

        Mail::assertQueued(
            BookingCancelledMail::class,
            fn (BookingCancelledMail $thu) => $thu->hasTo($chuaTra->customer_email),
        );
    }

    /** Thư phải dựng được thật, và phải nói rõ quyền từ chối — đó là phần quan trọng nhất của nó. */
    public function test_thu_bao_ghep_noi_ro_ngay_moi_va_quyen_hoan_du(): void
    {
        $don = $this->taoDon($this->nguon);

        $noiDung = (new ScheduleMergedMail(
            $don,
            $this->nguon->start_date,
            $this->dich->start_date,
            'Hai chuyen deu thieu khach nen don ve mot.',
        ))->render();

        $this->assertStringContainsString($this->dich->start_date->format('d/m/Y'), $noiDung);
        $this->assertStringContainsString('100%', $noiDung);
        $this->assertStringContainsString('Không đổi', $noiDung);
    }

    // --- Quyền hoàn đủ ------------------------------------------------------------------

    /**
     * Khách bị công ty dời ngày, không chịu ngày mới thì hoàn ĐỦ, bảng phí không áp.
     *
     * Khách mua ngày 20 mà công ty giao ngày 21; họ từ chối thì đó không phải hủy tự nguyện. Cùng
     * chuẩn với luồng hủy cả chuyến, nơi khách được chọn "hoàn đủ tiền".
     */
    public function test_khach_bi_ghep_roi_huy_thi_duoc_hoan_du(): void
    {
        $don = $this->taoDon($this->nguon);

        $this->service()->merge($this->nguon, $this->dich, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);

        $bang = app(\App\Services\CancellationPolicyService::class)->quote($don->fresh());

        $this->assertTrue($bang['moved_by_company']);
        $this->assertSame(100, $bang['refund_percent']);
        $this->assertEqualsWithDelta(0.0, $bang['cancellation_fee'], 0.01, 'Không thu phí hủy của người mình vừa đổi ngày.');
        $this->assertEqualsWithDelta((float) $don->total_amount, $bang['refund_amount'], 0.01);
    }

    /**
     * Nhưng khách TỰ xin đổi chuyến thì vẫn theo bảng phí như thường.
     *
     * Bài đối chứng: luật trên chỉ nói về thay đổi do công ty gây ra. Nới nó ra thành "hễ từng
     * chuyển chuyến là hoàn đủ" thì mở một đường lách - xin đổi ngày một lần rồi hủy, khỏi mất phí.
     */
    public function test_khach_tu_xin_doi_chuyen_thi_khong_duoc_hoan_du(): void
    {
        $don = $this->taoDon($this->dich);

        BookingTransfer::query()->create([
            'booking_id' => $don->id,
            'from_schedule_id' => $this->nguon->id,
            'to_schedule_id' => $this->dich->id,
            'from_tour_id' => $this->tour->id,
            'to_tour_id' => $this->tour->id,
            'initiated_by' => 'customer',
            'price_difference' => 0,
            'fee' => 0,
            'reason' => 'Khach ban viec rieng, xin doi ngay.',
            'approved_by' => $this->dieuHanh->id,
            'approved_at' => now(),
        ]);

        $bang = app(\App\Services\CancellationPolicyService::class)->quote($don->fresh());

        $this->assertFalse($bang['moved_by_company']);
        $this->assertLessThan(100, $bang['refund_percent']);
    }

    /** Bài quan trọng nhất: số chỗ phải dồn đúng và chuyến nguồn về 0. */
    public function test_so_cho_don_dung_ve_chuyen_dich(): void
    {
        $this->taoDon($this->nguon, khach: 4);
        $this->taoDon($this->dich, khach: 3);

        $this->service()->merge($this->nguon, $this->dich, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);

        $this->assertSame(0, (int) $this->nguon->fresh()->booked_people);
        $this->assertSame(7, (int) $this->dich->fresh()->booked_people);
    }

    public function test_sau_khi_ghep_thi_so_cho_van_nhat_quan(): void
    {
        $this->taoDon($this->nguon, khach: 4);
        $this->taoDon($this->dich, khach: 3);
        $this->taoDon($this->nguon, 'pending', 2);

        $this->service()->merge($this->nguon, $this->dich, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);

        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }

    public function test_chuyen_nguon_bi_huy_va_tro_toi_chuyen_dich(): void
    {
        $this->taoDon($this->nguon);

        $this->service()->merge($this->nguon, $this->dich, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);

        $nguon = $this->nguon->fresh();

        $this->assertSame(ScheduleStatus::Cancelled->value, $nguon->getRawOriginal('status'));
        $this->assertSame($this->dich->id, (int) $nguon->merged_into_schedule_id);
    }

    /**
     * Đơn chưa thanh toán thì hủy chứ không chuyển: khách chưa trả tiền nên chưa cam kết gì, và
     * chuyển họ sang một ngày họ chưa từng đồng ý là tự quyết thay khách.
     */
    public function test_don_chua_thanh_toan_thi_bi_huy_chu_khong_chuyen(): void
    {
        $donChuaTra = $this->taoDon($this->nguon, 'pending');

        $ketQua = $this->service()->merge($this->nguon, $this->dich, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);

        $this->assertSame(1, $ketQua['cancelled']);
        $this->assertSame(0, $ketQua['transferred']);

        $donChuaTra->refresh();

        $this->assertSame('cancelled', $donChuaTra->status);
        $this->assertSame($this->nguon->id, (int) $donChuaTra->tour_schedule_id, 'Đơn bị hủy thì ở nguyên chuyến cũ.');
        $this->assertTrue((bool) $donChuaTra->seats_released);
        $this->assertStringContainsString('đặt lại', $donChuaTra->cancel_reason);
    }

    // --- Điều kiện ----------------------------------------------------------------------

    public function test_khong_ghep_duoc_hai_tour_khac_nhau(): void
    {
        $tourKhac = Tour::factory()->create(['status' => 'active', 'number_of_days' => 2]);
        $chuyenKhacTour = $this->taoChuyen(now()->addDays(21), $tourKhac);

        $this->taoDon($this->nguon);

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->service()->merge($this->nguon, $chuyenKhacTour, 'Ghep sang tour khac.', $this->dieuHanh);
    }

    /** Tour riêng không ghép được: khách đã trả tiền để đi trọn chuyến của riêng họ. */
    public function test_tour_rieng_khong_ghep_duoc(): void
    {
        $this->tour->update(['type' => TourType::Private->value]);
        $this->taoDon($this->nguon);

        $duBao = $this->service()->preview($this->nguon->fresh(), $this->dich);

        $this->assertFalse($duBao['can_merge']);
        $this->assertStringContainsString('Tour riêng', $duBao['blocked_reason']);
    }

    public function test_lech_ngay_qua_xa_thi_tu_choi(): void
    {
        $chuyenXa = $this->taoChuyen(now()->addDays(30));
        $this->taoDon($this->nguon);

        $duBao = $this->service()->preview($this->nguon->fresh(), $chuyenXa);

        $this->assertFalse($duBao['can_merge']);
        $this->assertStringContainsString('ngày', $duBao['blocked_reason']);
    }

    public function test_chuyen_dich_khong_du_cho_thi_tu_choi(): void
    {
        $chuyenChat = $this->taoChuyen(now()->addDays(21), null, ['max_people' => 3]);
        $this->taoDon($this->nguon, khach: 5);

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->service()->merge($this->nguon->fresh(), $chuyenChat, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);
    }

    /**
     * Ghép phải xong trước hạn chốt của CẢ HAI chuyến.
     *
     * Mục đích của ghép là gửi một danh sách đúng thay vì hai danh sách sai. Ghép sau khi danh
     * sách đã gửi thì phải gọi hủy chuyến nguồn và xin thêm suất cho chuyến đích - hai lần làm
     * việc với nhà cung cấp, và có thể bị từ chối.
     */
    public function test_chuyen_nguon_qua_han_chot_thi_khong_ghep_duoc(): void
    {
        $this->taoDon($this->nguon);
        $this->nguon->update(['booking_deadline' => now()->subHour()]);

        $duBao = $this->service()->preview($this->nguon->fresh(), $this->dich);

        $this->assertFalse($duBao['can_merge']);
        $this->assertStringContainsString('hạn chốt', $duBao['blocked_reason']);
    }

    /**
     * Chuyến đích qua hạn chốt là trường hợp nghiêm trọng hơn: ghép thêm khách vào làm
     * booked_people vượt quá số suất đã cam kết với nhà cung cấp.
     */
    public function test_chuyen_dich_qua_han_chot_thi_khong_ghep_duoc(): void
    {
        $this->taoDon($this->nguon);
        $this->dich->update(['booking_deadline' => now()->subHour()]);

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->service()->merge($this->nguon->fresh(), $this->dich->fresh(), 'Hai chuyen deu thieu khach.', $this->dieuHanh);
    }

    /** Bị chặn vì hạn chốt thì hai chuyến phải giữ nguyên mọi thứ. */
    public function test_bi_chan_vi_han_chot_thi_hai_chuyen_giu_nguyen(): void
    {
        $don = $this->taoDon($this->nguon);
        $this->dich->update(['booking_deadline' => now()->subHour()]);

        try {
            $this->service()->merge($this->nguon->fresh(), $this->dich->fresh(), 'Ghep thu.', $this->dieuHanh);
        } catch (\App\Exceptions\BusinessRuleException) {
            // Bỏ qua, phần cần kiểm nằm bên dưới.
        }

        $this->assertSame(2, (int) $this->nguon->fresh()->booked_people);
        $this->assertSame($this->nguon->id, (int) $don->fresh()->tour_schedule_id);
        $this->assertSame(ScheduleStatus::Open->value, $this->nguon->fresh()->getRawOriginal('status'));
        $this->assertNull($this->nguon->fresh()->merged_into_schedule_id);
    }

    /** Chuyến qua hạn chốt không xuất hiện trong danh sách gợi ý ghép. */
    public function test_chuyen_qua_han_chot_khong_hien_trong_goi_y(): void
    {
        $this->taoDon($this->nguon);
        $this->dich->update(['booking_deadline' => now()->subHour()]);

        Sanctum::actingAs($this->dieuHanh);

        $response = $this->getJson("/api/admin/schedules/{$this->nguon->id}/merge-candidates")
            ->assertOk();

        $ids = array_column($response->json('data.candidates'), 'schedule_id');

        $this->assertNotContains($this->dich->id, $ids);
    }

    public function test_chuyen_dang_chay_thi_khong_ghep_duoc(): void
    {
        $this->nguon->update([
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subHours(3),
            'end_date' => now()->addDay(),
        ]);

        $duBao = $this->service()->preview($this->nguon->fresh(), $this->dich);

        $this->assertFalse($duBao['can_merge']);
    }

    /** Từ chối thì không được để lại dấu vết nào ở cả hai chuyến. */
    public function test_tu_choi_thi_hai_chuyen_giu_nguyen(): void
    {
        $chuyenChat = $this->taoChuyen(now()->addDays(21), null, ['max_people' => 3]);
        $don = $this->taoDon($this->nguon, khach: 5);

        try {
            $this->service()->merge($this->nguon->fresh(), $chuyenChat, 'Ghep thu.', $this->dieuHanh);
        } catch (\App\Exceptions\BusinessRuleException) {
            // Bỏ qua, phần cần kiểm nằm bên dưới.
        }

        $this->assertSame(5, (int) $this->nguon->fresh()->booked_people);
        $this->assertSame(0, (int) $chuyenChat->fresh()->booked_people);
        $this->assertSame($this->nguon->id, (int) $don->fresh()->tour_schedule_id);
        $this->assertSame(ScheduleStatus::Open->value, $this->nguon->fresh()->getRawOriginal('status'));
    }

    // --- Ghép dây chuyền ----------------------------------------------------------------

    /**
     * Ghép A vào B rồi B vào C thì khách của A phải nhìn thấy C, không phải B.
     *
     * Kiểm qua đúng cơ chế thật: mỗi lần ghép, `moveBooking()` trỏ lại `tour_schedule_id` của đơn
     * sang chuyến đích, nên sau hai lần ghép đơn đã nằm ở C — khách tra cứu là thấy ngay ngày mới,
     * không cần ai đi dò ngược chuỗi ghép.
     *
     * Bài này trước đây gọi `ScheduleMergeService::finalScheduleOf()`, một hàm đi theo chuỗi
     * `merged_into_schedule_id` để tìm chuyến cuối. Hàm ấy **không có nơi nào trong ứng dụng gọi
     * tới** — chỉ bài kiểm thử này gọi — nên nó chứng minh một đường đi mà khách chưa bao giờ đi
     * qua, trong khi đường khách thật sự đi thì không bài nào kiểm. Nay đổi lại cho đúng.
     */
    public function test_ghep_day_chuyen_thi_don_nam_o_chuyen_cuoi_cung(): void
    {
        $chuyenC = $this->taoChuyen(now()->addDays(22));

        $don = $this->taoDon($this->nguon);
        $this->taoDon($this->dich);

        $this->service()->merge($this->nguon, $this->dich, 'Ghep A vao B vi thieu khach.', $this->dieuHanh);
        $this->service()->merge($this->dich->fresh(), $chuyenC, 'Ghep B vao C vi van thieu khach.', $this->dieuHanh);

        $this->assertSame($chuyenC->id, (int) $don->fresh()->tour_schedule_id);
        $this->assertSame(
            $chuyenC->start_date->toDateTimeString(),
            $don->fresh()->departure_date,
            'Ngày khởi hành trên đơn phải là ngày của chuyến cuối, vì đó là ngày khách sẽ đi.',
        );
    }

    // --- Dấu vết và API -----------------------------------------------------------------

    public function test_moi_don_chuyen_deu_co_ban_ghi_va_khong_thu_phi(): void
    {
        $don = $this->taoDon($this->nguon);

        $this->service()->merge($this->nguon, $this->dich, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);

        $banGhi = BookingTransfer::query()->where('booking_id', $don->id)->first();

        $this->assertNotNull($banGhi);
        $this->assertSame('company', $banGhi->initiated_by);
        $this->assertEquals(0, (float) $banGhi->fee, 'Ghép do hãng khởi xướng nên không thu phí.');
        $this->assertEquals(0, (float) $banGhi->price_difference, 'Cùng tour nên giá không đổi.');
    }

    public function test_api_liet_ke_chuyen_co_the_ghep(): void
    {
        $this->taoDon($this->nguon);
        Sanctum::actingAs($this->dieuHanh);

        $response = $this->getJson("/api/admin/schedules/{$this->nguon->id}/merge-candidates")
            ->assertOk();

        $ids = array_column($response->json('data.candidates'), 'schedule_id');

        $this->assertContains($this->dich->id, $ids);
    }

    public function test_api_ghep_chuyen_thanh_cong(): void
    {
        $this->taoDon($this->nguon);
        Sanctum::actingAs($this->dieuHanh);

        $this->postJson("/api/admin/schedules/{$this->nguon->id}/merge", [
            'to_schedule_id' => $this->dich->id,
            'reason' => 'Hai chuyen deu thieu khach toi thieu nen don ve mot.',
        ])->assertOk();

        $this->assertSame(ScheduleStatus::Cancelled->value, $this->nguon->fresh()->getRawOriginal('status'));
    }

    public function test_khach_khong_ghep_duoc_chuyen(): void
    {
        $khach = User::create([
            'name' => 'Khach',
            'email' => 'khach-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($khach);

        $this->postJson("/api/admin/schedules/{$this->nguon->id}/merge", [
            'to_schedule_id' => $this->dich->id,
            'reason' => 'Toi muon tu ghep chuyen.',
        ])->assertStatus(403);
    }
}
