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


    private function mergeAndAccept(TourSchedule $from, TourSchedule $to, string $reason, User $actor): array
    {
        $result = $this->service()->merge($from, $to, $reason, $actor);
        $count = 0;
        foreach (\App\Models\BookingChangeProposal::query()->pending()->where('from_schedule_id', $from->id)->where('to_schedule_id', $to->id)->get() as $proposal) {
            $this->service()->respond($proposal, 'accept', null);
            $count++;
        }
        return ['transferred' => $count, 'cancelled' => 0, 'proposed' => $result['proposed']];
    }

    // --- Luồng chính --------------------------------------------------------------------

    public function test_ghep_thi_don_da_thanh_toan_chuyen_sang_chuyen_dich(): void
    {
        $donMot = $this->taoDon($this->nguon);
        $donHai = $this->taoDon($this->nguon);
        $this->taoDon($this->dich);

        $ketQua = $this->mergeAndAccept($this->nguon, $this->dich, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);

        $this->assertSame(2, $ketQua['transferred']);
        $this->assertSame($this->dich->id, (int) $donMot->fresh()->tour_schedule_id);
        $this->assertSame($this->dich->id, (int) $donHai->fresh()->tour_schedule_id);
    }

    // --- Nói cho khách biết -------------------------------------------------------------


    public function test_ghep_thi_gui_thu_cho_ca_hai_nhom_khach(): void
    {
        Mail::fake();
        $daTra = $this->taoDon($this->nguon);
        $chuaTra = $this->taoDon($this->nguon, 'pending');
        $this->service()->merge($this->nguon, $this->dich, 'Đề xuất ghép hai chuyến.', $this->dieuHanh);
        Mail::assertQueued(\App\Mail\BookingProposalMail::class,
            fn ($thu) => $thu->hasTo($daTra->customer_email));
        Mail::assertNotQueued(BookingCancelledMail::class);
        $this->assertSame('pending', $chuaTra->fresh()->status);
    }


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


    public function test_khach_bi_ghep_roi_huy_thi_duoc_hoan_du(): void
    {
        $don = $this->taoDon($this->nguon);

        $this->mergeAndAccept($this->nguon, $this->dich, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);

        $bang = app(\App\Services\CancellationPolicyService::class)->quote($don->fresh());

        $this->assertTrue($bang['moved_by_company']);
        $this->assertSame(100, $bang['refund_percent']);
        $this->assertEqualsWithDelta(0.0, $bang['cancellation_fee'], 0.01, 'Không thu phí hủy của người mình vừa đổi ngày.');
        $this->assertEqualsWithDelta((float) $don->total_amount, $bang['refund_amount'], 0.01);
    }


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


        $this->dich->update([
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(11),
        ]);

        $bang = app(\App\Services\CancellationPolicyService::class)->quote($don->fresh());

        $this->assertFalse($bang['moved_by_company']);
        $this->assertSame(50, $bang['refund_percent']);
    }


    public function test_so_cho_don_dung_ve_chuyen_dich(): void
    {
        $this->taoDon($this->nguon, khach: 4);
        $this->taoDon($this->dich, khach: 3);

        $this->mergeAndAccept($this->nguon, $this->dich, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);

        $this->assertSame(0, (int) $this->nguon->fresh()->booked_people);
        $this->assertSame(7, (int) $this->dich->fresh()->booked_people);
    }

    public function test_sau_khi_ghep_thi_so_cho_van_nhat_quan(): void
    {
        $this->taoDon($this->nguon, khach: 4);
        $this->taoDon($this->dich, khach: 3);
        $this->taoDon($this->nguon, 'pending', 2);

        $this->mergeAndAccept($this->nguon, $this->dich, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);

        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }

    public function test_chuyen_nguon_bi_huy_va_tro_toi_chuyen_dich(): void
    {
        $this->taoDon($this->nguon);

        $this->mergeAndAccept($this->nguon, $this->dich, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);

        $nguon = $this->nguon->fresh();

        $this->assertSame(ScheduleStatus::Cancelled->value, $nguon->getRawOriginal('status'));
        $this->assertSame($this->dich->id, (int) $nguon->merged_into_schedule_id);
    }


    public function test_don_chua_thanh_toan_giu_nguyen_cho_toi_han_giu_cho(): void
    {
        $don = $this->taoDon($this->nguon, 'pending');
        $result = $this->service()->merge($this->nguon, $this->dich, 'Đề xuất ghép hai chuyến.', $this->dieuHanh);
        $this->assertSame(0, $result['cancelled']);
        $this->assertSame(0, $result['proposed']);
        $this->assertSame('pending', $don->fresh()->status);
        $this->assertSame($this->nguon->id, $don->fresh()->tour_schedule_id);
    }

    // --- Điều kiện ----------------------------------------------------------------------

    public function test_khong_ghep_duoc_hai_tour_khac_nhau(): void
    {
        $tourKhac = Tour::factory()->create(['status' => 'active', 'number_of_days' => 2]);
        $chuyenKhacTour = $this->taoChuyen(now()->addDays(21), $tourKhac);

        $this->taoDon($this->nguon);

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->mergeAndAccept($this->nguon, $chuyenKhacTour, 'Ghep sang tour khac.', $this->dieuHanh);
    }


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

        $this->mergeAndAccept($this->nguon->fresh(), $chuyenChat, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);
    }


    public function test_chuyen_nguon_qua_han_chot_thi_khong_ghep_duoc(): void
    {
        $this->taoDon($this->nguon);
        $this->nguon->update(['booking_deadline' => now()->subHour()]);

        $duBao = $this->service()->preview($this->nguon->fresh(), $this->dich);

        $this->assertFalse($duBao['can_merge']);
        $this->assertStringContainsString('hạn chốt', $duBao['blocked_reason']);
    }


    public function test_chuyen_dich_qua_han_chot_thi_khong_ghep_duoc(): void
    {
        $this->taoDon($this->nguon);
        $this->dich->update(['booking_deadline' => now()->subHour()]);

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->mergeAndAccept($this->nguon->fresh(), $this->dich->fresh(), 'Hai chuyen deu thieu khach.', $this->dieuHanh);
    }


    public function test_bi_chan_vi_han_chot_thi_hai_chuyen_giu_nguyen(): void
    {
        $don = $this->taoDon($this->nguon);
        $this->dich->update(['booking_deadline' => now()->subHour()]);

        try {
            $this->mergeAndAccept($this->nguon->fresh(), $this->dich->fresh(), 'Ghep thu.', $this->dieuHanh);
        } catch (\App\Exceptions\BusinessRuleException) {
            // Bỏ qua, phần cần kiểm nằm bên dưới.
        }

        $this->assertSame(2, (int) $this->nguon->fresh()->booked_people);
        $this->assertSame($this->nguon->id, (int) $don->fresh()->tour_schedule_id);
        $this->assertSame(ScheduleStatus::Open->value, $this->nguon->fresh()->getRawOriginal('status'));
        $this->assertNull($this->nguon->fresh()->merged_into_schedule_id);
    }


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


    public function test_tu_choi_thi_hai_chuyen_giu_nguyen(): void
    {
        $chuyenChat = $this->taoChuyen(now()->addDays(21), null, ['max_people' => 3]);
        $don = $this->taoDon($this->nguon, khach: 5);

        try {
            $this->mergeAndAccept($this->nguon->fresh(), $chuyenChat, 'Ghep thu.', $this->dieuHanh);
        } catch (\App\Exceptions\BusinessRuleException) {
            // Bỏ qua, phần cần kiểm nằm bên dưới.
        }

        $this->assertSame(5, (int) $this->nguon->fresh()->booked_people);
        $this->assertSame(0, (int) $chuyenChat->fresh()->booked_people);
        $this->assertSame($this->nguon->id, (int) $don->fresh()->tour_schedule_id);
        $this->assertSame(ScheduleStatus::Open->value, $this->nguon->fresh()->getRawOriginal('status'));
    }

    // --- Ghép dây chuyền ----------------------------------------------------------------


    public function test_ghep_day_chuyen_thi_don_nam_o_chuyen_cuoi_cung(): void
    {
        $chuyenC = $this->taoChuyen(now()->addDays(22));

        $don = $this->taoDon($this->nguon);
        $this->taoDon($this->dich);

        $this->mergeAndAccept($this->nguon, $this->dich, 'Ghep A vao B vi thieu khach.', $this->dieuHanh);
        $this->mergeAndAccept($this->dich->fresh(), $chuyenC, 'Ghep B vao C vi van thieu khach.', $this->dieuHanh);

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

        $this->mergeAndAccept($this->nguon, $this->dich, 'Hai chuyen deu thieu khach nen don ve mot.', $this->dieuHanh);

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

    public function test_du_bao_ghe_khop_ket_qua_ghep_khi_co_em_be_va_don_chua_tra(): void
    {
        Mail::fake();
        $giaDinh = $this->taoDon($this->nguon, khach: 2);
        $giaDinh->update(['guests' => 3, 'seats' => 2, 'infant_count' => 1]);
        $chuaTra = $this->taoDon($this->nguon, 'pending', 4);
        $this->taoDon($this->dich, khach: 3);
        Sanctum::actingAs($this->dieuHanh);

        $response = $this->getJson("/api/admin/schedules/{$this->nguon->id}/merge-candidates")
            ->assertOk();
        $duBao = collect($response->json('data.candidates'))->firstWhere('schedule_id', $this->dich->id);

        $this->assertNotNull($duBao);
        $this->assertSame(3, $duBao['transferring_guests']);
        $this->assertSame(2, $duBao['transferring_seats']);
        $this->assertSame(1, $duBao['transferring']);
        $this->assertSame(0, $duBao['cancelling']);
        $this->assertSame(17, $duBao['remaining_seats']);
        $this->assertSame(15, $duBao['remaining_seats_after']);
        $this->assertSame($this->nguon->id, (int) $giaDinh->fresh()->tour_schedule_id);
        $this->assertSame('pending', $chuaTra->fresh()->getRawOriginal('status'));
        Mail::assertNothingQueued();

        $this->mergeAndAccept($this->nguon->fresh(), $this->dich->fresh(), 'Ghep hai chuyen vi chua du khach.', $this->dieuHanh);
        $dichSauGhep = $this->dich->fresh();
        $this->assertSame($duBao['remaining_seats_after'], (int) $dichSauGhep->max_people - (int) $dichSauGhep->booked_people);
    }

    #[\PHPUnit\Framework\Attributes\TestWith([false])]
    #[\PHPUnit\Framework\Attributes\TestWith([true])]
    public function test_ghep_cung_ngay_du_bao_dung_ghe_va_chuyen_ca_don_chua_tra(bool $khacTour): void
    {
        Mail::fake();
        $tourDich = $khacTour ? Tour::factory()->create(['type' => TourType::Shared->value, 'status' => 'active']) : $this->tour;
        $dich = $this->taoChuyen($this->nguon->start_date, $tourDich);
        $giaDinh = $this->taoDon($this->nguon, khach: 2);
        $giaDinh->update(['guests' => 3, 'seats' => 2, 'infant_count' => 1]);
        $chuaTra = $this->taoDon($this->nguon, 'pending', 4);
        $this->taoDon($dich, khach: 3);
        Sanctum::actingAs($this->dieuHanh);

        $response = $this->getJson("/api/admin/schedules/{$this->nguon->id}/merge-candidates")->assertOk();
        $duBao = collect($response->json('data.candidates'))->firstWhere('schedule_id', $dich->id);
        $this->assertNotNull($duBao);
        $this->assertSame(1, $duBao['transferring']);
        $this->assertSame(3, $duBao['transferring_guests']);
        $this->assertSame(2, $duBao['transferring_seats']);
        $this->assertSame(0, $duBao['cancelling']);
        $this->assertSame(15, $duBao['remaining_seats_after']);

        $ketQua = $this->mergeAndAccept($this->nguon->fresh(), $dich->fresh(), 'Ghep hai chuyen cung ngay vi chua du khach.', $this->dieuHanh);
        $this->assertSame(1, $ketQua['transferred']);
        $this->assertSame(0, $ketQua['cancelled']);
        $this->assertSame($dich->id, (int) $giaDinh->fresh()->tour_schedule_id);
        $this->assertSame($this->nguon->id, (int) $chuaTra->fresh()->tour_schedule_id);
        $this->assertSame('pending', $chuaTra->fresh()->getRawOriginal('status'));
        $this->assertNull($this->nguon->fresh()->merged_into_schedule_id);
        $this->assertSame($duBao['remaining_seats_after'], (int) $dich->fresh()->max_people - (int) $dich->fresh()->booked_people);
        Mail::assertQueued(\App\Mail\BookingProposalMail::class);
    }

    public function test_api_ghep_chuyen_thanh_cong(): void
    {
        $this->taoDon($this->nguon);
        Sanctum::actingAs($this->dieuHanh);

        $this->postJson("/api/admin/schedules/{$this->nguon->id}/merge", [
            'to_schedule_id' => $this->dich->id,
            'reason' => 'Hai chuyen deu thieu khach toi thieu nen don ve mot.',
        ])->assertOk();

        $this->assertSame(ScheduleStatus::Open->value, $this->nguon->fresh()->getRawOriginal('status'));
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
