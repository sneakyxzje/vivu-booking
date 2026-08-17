<?php

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SurchargeStatus;
use App\Enums\TourType;
use App\Models\Booking;
use App\Models\BookingSurcharge;
use App\Models\ScheduleIncident;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * O - Sự cố dọc đường và chi phí phát sinh.
 *
 * Tình huống hội đồng nêu: đoàn ra biển gặp bão, phải đổi sang chương trình khác đắt hơn.
 *
 * **Điều bộ test này giữ là tách quyền, không phải phép tính.** Người ở hiện trường báo cáo những
 * gì nhìn thấy; người ở văn phòng quyết ai trả bao nhiêu. Nếu về sau có ai gộp hai vai đó lại cho
 * tiện thì những bài dưới đây phải đỏ.
 */
class IncidentTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private User $guide;
    private Tour $tour;
    private TourSchedule $chuyen;
    private Booking $don;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dieuHanh = $this->taoNguoi('admin');
        $this->guide = $this->taoNguoi('guide');

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 3,
            'adult_price' => 2_000_000,
        ]);

        // Đoàn đang đi: sự cố dọc đường chỉ ghi được khi đã lên đường.
        $this->chuyen = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'booking_deadline' => now()->subDays(4),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ]);

        $this->chuyen->guides()->sync([$this->guide->id]);

        $this->don = $this->taoDon();
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

    private function taoDon(?TourSchedule $schedule = null): Booking
    {
        $schedule ??= $this->chuyen;
        $schedule->increment('booked_people', 2);

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $schedule->tour_id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach ' . Str::random(4),
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 4_000_000,
            'status' => 'confirmed',
            'paid_at' => now()->subDays(3),
            'confirmed_at' => now()->subDays(3),
        ]);
    }

    private function baoCao(array $ghiDe = []): array
    {
        return array_merge([
            'type' => 'weather',
            'severity' => 'high',
            'occurred_at' => now()->subHours(2)->toDateTimeString(),
            'description' => 'Bao vao dat lien, tau khong ra dao duoc, doan phai o lai bo them mot dem.',
        ], $ghiDe);
    }

    // --- Hướng dẫn viên báo cáo -----------------------------------------------------------

    public function test_huong_dan_vien_bao_cao_duoc_su_co_cua_chuyen_minh_dan(): void
    {
        Sanctum::actingAs($this->guide);

        $this->postJson('/api/guide/schedules/' . $this->chuyen->id . '/incidents', $this->baoCao())
            ->assertOk()
            ->assertJsonPath('data.status', IncidentStatus::Reported->value);

        $this->assertDatabaseHas('schedule_incidents', [
            'tour_schedule_id' => $this->chuyen->id,
            'reported_by' => $this->guide->id,
            'status' => IncidentStatus::Reported->value,
        ]);
    }

    /**
     * Đây là bài quan trọng nhất của nhóm.
     *
     * Hướng dẫn viên gửi kèm các trường tiền thì máy chủ phải bỏ qua hoàn toàn, chứ không phải
     * ghi rồi chờ ai đó sửa lại. Người ở hiện trường đang chịu áp lực của khách; để họ quyết mức
     * thu là đặt họ vào chỗ không ai nên bị đặt vào.
     */
    public function test_huong_dan_vien_khong_dat_duoc_muc_phi_du_co_gui_len(): void
    {
        Sanctum::actingAs($this->guide);

        $this->postJson(
            '/api/guide/schedules/' . $this->chuyen->id . '/incidents',
            $this->baoCao([
                'cost_delta' => 5_000_000,
                'who_bears' => 'customer',
                'resolution' => 'Thu them cua khach 5 trieu.',
            ]),
        )->assertOk();

        $sc = ScheduleIncident::query()->latest('id')->first();

        $this->assertNull($sc->cost_delta, 'Hướng dẫn viên không đặt được số tiền.');
        $this->assertNull($sc->who_bears, 'Hướng dẫn viên không quyết được ai chịu.');
        $this->assertNull($sc->resolution, 'Phương án là việc của điều hành.');
        $this->assertSame(0, BookingSurcharge::query()->count(), 'Không sinh khoản thu nào.');
    }

    public function test_khong_bao_cao_duoc_cho_chuyen_minh_khong_dan(): void
    {
        $nguoiKhac = $this->taoNguoi('guide');

        Sanctum::actingAs($nguoiKhac);

        $this->postJson('/api/guide/schedules/' . $this->chuyen->id . '/incidents', $this->baoCao())
            ->assertStatus(422);

        $this->assertSame(0, ScheduleIncident::query()->count());
    }

    public function test_chuyen_chua_khoi_hanh_thi_khong_bao_su_co_doc_duong(): void
    {
        $chuaDi = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(12),
            'booking_deadline' => now()->addDays(7),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ]);

        $chuaDi->guides()->sync([$this->guide->id]);

        Sanctum::actingAs($this->guide);

        $this->postJson('/api/guide/schedules/' . $chuaDi->id . '/incidents', $this->baoCao())
            ->assertStatus(422);
    }

    /** Mất sóng giữa biển là chuyện thường, nhưng báo muộn phải được đánh dấu. */
    public function test_bao_muon_thi_danh_dau_la_ghi_bu(): void
    {
        Sanctum::actingAs($this->guide);

        $this->postJson(
            '/api/guide/schedules/' . $this->chuyen->id . '/incidents',
            $this->baoCao(['occurred_at' => now()->subHours(20)->toDateTimeString()]),
        )->assertOk()->assertJsonPath('data.reported_late', true);
    }

    public function test_thoi_diem_xay_ra_khong_duoc_o_tuong_lai(): void
    {
        Sanctum::actingAs($this->guide);

        $this->postJson(
            '/api/guide/schedules/' . $this->chuyen->id . '/incidents',
            $this->baoCao(['occurred_at' => now()->addHours(3)->toDateTimeString()]),
        )->assertStatus(422);
    }

    // --- Điều hành quyết chi phí ----------------------------------------------------------

    private function taoSuCo(): ScheduleIncident
    {
        Sanctum::actingAs($this->guide);

        $this->postJson('/api/guide/schedules/' . $this->chuyen->id . '/incidents', $this->baoCao())
            ->assertOk();

        return ScheduleIncident::query()->latest('id')->first();
    }

    public function test_dieu_hanh_ghi_phuong_an_va_phan_bo_chi_phi(): void
    {
        $sc = $this->taoSuCo();

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/incidents/' . $sc->id . '/resolve', [
            'resolution' => 'Doi sang chuong trinh tham quan trong bo, o them mot dem tai khach san cu.',
            'cost_delta' => 8_000_000,
            'who_bears' => 'shared',
            'charges' => [[
                'booking_id' => $this->don->id,
                'kind' => 'surcharge',
                'amount' => 1_200_000,
                'reason' => 'Mot dem phong doi va hai bua an phat sinh.',
            ]],
        ])->assertOk();

        $sc->refresh();

        $this->assertSame(IncidentStatus::Reviewed, $sc->status);
        $this->assertSame($this->dieuHanh->id, $sc->reviewed_by);
        $this->assertSame(8_000_000.0, (float) $sc->cost_delta);

        $khoan = BookingSurcharge::query()->first();

        $this->assertSame($this->don->id, $khoan->booking_id);
        $this->assertSame(1_200_000.0, (float) $khoan->amount);
    }

    /** Khoản vừa lập chưa có hiệu lực: phải qua một bước duyệt riêng. */
    public function test_khoan_vua_lap_o_trang_thai_cho_duyet(): void
    {
        $sc = $this->taoSuCo();

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/incidents/' . $sc->id . '/resolve', [
            'resolution' => 'Doi sang chuong trinh tham quan trong bo, o them mot dem tai khach san cu.',
            'charges' => [[
                'booking_id' => $this->don->id,
                'kind' => 'surcharge',
                'amount' => 1_200_000,
                'reason' => 'Mot dem phong doi va hai bua an phat sinh.',
            ]],
        ])->assertOk();

        $khoan = BookingSurcharge::query()->first();

        $this->assertSame(SurchargeStatus::Pending, $khoan->status);
        $this->assertFalse($khoan->status->coHieuLuc());

        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/approve')->assertOk();

        $khoan->refresh();

        $this->assertSame(SurchargeStatus::Approved, $khoan->status);
        $this->assertTrue($khoan->status->coHieuLuc());
        $this->assertSame($this->dieuHanh->id, $khoan->approved_by);
    }

    /** Phương án ghi hãng chịu mà vẫn lập khoản thu của khách là tự mâu thuẫn. */
    public function test_hang_chiu_thi_khong_lap_duoc_khoan_thu_cua_khach(): void
    {
        $sc = $this->taoSuCo();

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/incidents/' . $sc->id . '/resolve', [
            'resolution' => 'Xe hong giua duong, thue xe khac dua doan di tiep theo dung lich trinh.',
            'who_bears' => 'company',
            'charges' => [[
                'booking_id' => $this->don->id,
                'kind' => 'surcharge',
                'amount' => 500_000,
                'reason' => 'Chi phi xe thay the.',
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, BookingSurcharge::query()->count());
    }

    public function test_khong_lap_duoc_khoan_cho_don_khong_thuoc_chuyen(): void
    {
        $sc = $this->taoSuCo();

        $chuyenKhac = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(20),
            'end_date' => now()->addDays(22),
            'booking_deadline' => now()->addDays(17),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ]);

        $donLa = $this->taoDon($chuyenKhac);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/incidents/' . $sc->id . '/resolve', [
            'resolution' => 'Doi sang chuong trinh tham quan trong bo, o them mot dem tai khach san cu.',
            'charges' => [[
                'booking_id' => $donLa->id,
                'kind' => 'surcharge',
                'amount' => 500_000,
                'reason' => 'Nham don.',
            ]],
        ])->assertStatus(422);
    }

    /** Khách không đồng ý thì miễn được, nhưng phải ghi lý do — không bao giờ bỏ khách lại. */
    public function test_mien_khoan_thu_phai_ghi_ly_do(): void
    {
        $sc = $this->taoSuCo();

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/incidents/' . $sc->id . '/resolve', [
            'resolution' => 'Doi sang chuong trinh tham quan trong bo, o them mot dem tai khach san cu.',
            'charges' => [[
                'booking_id' => $this->don->id,
                'kind' => 'surcharge',
                'amount' => 1_200_000,
                'reason' => 'Mot dem phong doi va hai bua an phat sinh.',
            ]],
        ])->assertOk();

        $khoan = BookingSurcharge::query()->first();

        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/waive')->assertStatus(422);

        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/waive', [
            'reason' => 'Khach khong dong y, cong ty chiu de giu quan he.',
        ])->assertOk();

        $this->assertSame(SurchargeStatus::Waived, $khoan->fresh()->status);
    }

    /** Quyết lại thì thay khoản chờ duyệt, nhưng giữ nguyên khoản đã duyệt. */
    public function test_quyet_lai_khong_xoa_khoan_da_duyet(): void
    {
        $sc = $this->taoSuCo();
        $donHai = $this->taoDon();

        Sanctum::actingAs($this->dieuHanh);

        $chung = 'Doi sang chuong trinh tham quan trong bo, o them mot dem tai khach san cu.';

        $this->postJson('/api/admin/incidents/' . $sc->id . '/resolve', [
            'resolution' => $chung,
            'charges' => [
                ['booking_id' => $this->don->id, 'kind' => 'surcharge', 'amount' => 1_200_000, 'reason' => 'Phong va an them.'],
                ['booking_id' => $donHai->id, 'kind' => 'surcharge', 'amount' => 1_200_000, 'reason' => 'Phong va an them.'],
            ],
        ])->assertOk();

        $daDuyet = BookingSurcharge::query()->where('booking_id', $this->don->id)->first();
        $this->putJson('/api/admin/surcharges/' . $daDuyet->id . '/approve')->assertOk();

        // Quyết lại, lần này chỉ còn một khoản.
        $this->postJson('/api/admin/incidents/' . $sc->id . '/resolve', [
            'resolution' => $chung . ' Da ra soat lai muc thu.',
            'charges' => [
                ['booking_id' => $donHai->id, 'kind' => 'surcharge', 'amount' => 800_000, 'reason' => 'Ra soat lai.'],
            ],
        ])->assertOk();

        $this->assertNotNull(
            $daDuyet->fresh(),
            'Khoản đã duyệt là thứ đã nói với khách, không được xóa lặng lẽ.',
        );

        $this->assertSame(
            800_000.0,
            (float) BookingSurcharge::query()->where('booking_id', $donHai->id)->first()->amount,
        );
    }
}
