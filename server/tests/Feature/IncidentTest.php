<?php

namespace Tests\Feature;

use App\Enums\CostBearer;
use App\Enums\IncidentStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SurchargeStatus;
use App\Enums\TourType;
use App\Models\Booking;
use App\Models\BookingPayment;
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
            $this->baoCao([
                'occurred_at' => \App\Support\GioVietNam::bayGio()->addHours(3)->toDateTimeString(),
            ]),
        )->assertStatus(422);
    }

    /**
     * Giờ gõ từ trình duyệt là giờ Việt Nam, phải chấp nhận được.
     *
     * Ứng dụng chạy UTC còn ô datetime-local gửi lên giờ treo tường Việt Nam. So thẳng với now()
     * thì mọi thời điểm trong 7 tiếng vừa qua đều bị coi là tương lai - tức gần như cả ngày làm
     * việc, và hướng dẫn viên không báo được sự cố nào.
     *
     * Bộ kiểm thử cũ không bắt được vì nó dựng dữ liệu bằng chính now(), tức cả hai vế cùng UTC
     * nên lệch bao nhiêu cũng triệt tiêu. Bài này cố ý gửi đúng thứ trình duyệt gửi.
     */
    public function test_gio_go_tu_trinh_duyet_la_gio_viet_nam_thi_van_bao_duoc(): void
    {
        Sanctum::actingAs($this->guide);

        $nhuTrinhDuyetGui = now(\App\Support\GioVietNam::MUI_GIO)
            ->subMinutes(5)
            ->format('Y-m-d H:i:s');

        $this->postJson(
            '/api/guide/schedules/' . $this->chuyen->id . '/incidents',
            $this->baoCao(['occurred_at' => $nhuTrinhDuyetGui]),
        )->assertOk()->assertJsonPath('data.reported_late', false);
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

    // --- Ai chịu chi phí, tính theo TỪNG khoản --------------------------------------------

    /**
     * Bài trung tâm của phần này: một cơn bão, ba khoản, ba người chịu khác nhau.
     *
     * Trước đây `who_bears` nằm trên sự cố nên tình huống thật nhất của cả nhóm O lại là tình
     * huống không ghi được. Đặt "hãng chịu" cho cơn bão thì không lập nổi khoản khách trả cho
     * đêm phòng ở thêm, dù đêm ấy đúng là khách chịu; đặt "khách chịu" thì ngược lại, chiếc xe
     * thuê thay tàu bỗng thành tiền của khách.
     */
    public function test_mot_su_co_sinh_ba_khoan_ba_nguoi_chiu_khac_nhau(): void
    {
        $sc = $this->taoSuCo();

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/incidents/' . $sc->id . '/resolve', [
            'resolution' => 'Thue xe chay duong bo thay tau, o them mot dem, cat buoi tham quan dao.',
            'who_bears' => 'company',
            'charges' => [
                [
                    'booking_id' => $this->don->id,
                    'kind' => 'surcharge',
                    'who_bears' => 'customer',
                    'amount' => 1_200_000,
                    'reason' => 'Mot dem phong va hai bua an ngoai lich trinh.',
                ],
                [
                    'booking_id' => $this->don->id,
                    'kind' => 'refund',
                    'who_bears' => 'company',
                    'amount' => 600_000,
                    'reason' => 'Buoi tham quan dao da ban nhung khong di duoc.',
                ],
            ],
        ])->assertOk();

        $khoanThu = BookingSurcharge::query()->where('kind', 'surcharge')->firstOrFail();
        $khoanHoan = BookingSurcharge::query()->where('kind', 'refund')->firstOrFail();

        $this->assertSame(
            CostBearer::Customer,
            $khoanThu->who_bears,
            'Đêm phòng ở thêm là tiêu dùng cá nhân, khách chịu.',
        );

        $this->assertSame(
            CostBearer::Company,
            $khoanHoan->who_bears,
            'Phần chương trình đã bán mà không giao được thì hãng trả lại.',
        );

        $this->assertSame(
            CostBearer::Company,
            $sc->fresh()->who_bears,
            'Giá trị trên sự cố vẫn còn, nhưng chỉ còn là mặc định gợi ý.',
        );
    }

    /** Dòng không nói rõ thì lùi về mặc định của phương án, không để trống. */
    public function test_khoan_khong_ghi_nguoi_chiu_thi_lay_theo_phuong_an(): void
    {
        $sc = $this->taoSuCo();

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/incidents/' . $sc->id . '/resolve', [
            'resolution' => 'Doi sang chuong trinh trong bo, khach chiu phan an o phat sinh.',
            'who_bears' => 'customer',
            'charges' => [[
                'booking_id' => $this->don->id,
                'kind' => 'surcharge',
                'amount' => 900_000,
                'reason' => 'Mot dem phong doi.',
            ]],
        ])->assertOk();

        $this->assertSame(
            CostBearer::Customer,
            BookingSurcharge::query()->firstOrFail()->who_bears,
        );
    }

    /**
     * Mâu thuẫn phải chặn ở mức DÒNG, không phải mức sự cố.
     *
     * Phương án ghi mặc định "khách chịu", nhưng chính dòng này ghi "hãng chịu" mà vẫn đòi thu
     * tiền khách. Đó là hai câu ngược nhau trong một dòng.
     */
    public function test_dong_ghi_hang_chiu_thi_khong_thu_duoc_tien_khach(): void
    {
        $sc = $this->taoSuCo();

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/incidents/' . $sc->id . '/resolve', [
            'resolution' => 'Thue xe chay duong bo thay tau, chi phi van chuyen do hang chiu.',
            'who_bears' => 'customer',
            'charges' => [[
                'booking_id' => $this->don->id,
                'kind' => 'surcharge',
                'who_bears' => 'company',
                'amount' => 3_000_000,
                'reason' => 'Thue xe 45 cho chay duong bo.',
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, BookingSurcharge::query()->count());
    }

    // --- Thu tiền: khoản duyệt xong phải thành tiền thật ----------------------------------

    private function taoKhoanDaDuyet(string $kind = 'surcharge', float $soTien = 1_200_000): BookingSurcharge
    {
        $sc = $this->taoSuCo();

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/incidents/' . $sc->id . '/resolve', [
            'resolution' => 'Doi sang chuong trinh trong bo, o them mot dem tai khach san cu.',
            'who_bears' => $kind === 'surcharge' ? 'customer' : 'company',
            'charges' => [[
                'booking_id' => $this->don->id,
                'kind' => $kind,
                'amount' => $soTien,
                'reason' => 'Mot dem phong doi va hai bua an phat sinh.',
            ]],
        ])->assertOk();

        $khoan = BookingSurcharge::query()->latest('id')->firstOrFail();

        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/approve')->assertOk();

        return $khoan->fresh();
    }

    public function test_thu_tien_thi_ghi_mot_dong_vao_so_giao_dich(): void
    {
        $khoan = $this->taoKhoanDaDuyet();

        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/consent', [
            'note' => 'Khach dong y, huong dan vien giai thich tai quay le tan.',
        ])->assertOk();

        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/settle', [
            'method' => 'cash',
            'note' => 'Huong dan vien thu tien mat tai khach san.',
        ])->assertOk();

        $this->assertSame(
            SurchargeStatus::Paid,
            $khoan->fresh()->status,
            'Trước đây trạng thái này khai báo rồi nhưng không dòng mã nào đặt được nó.',
        );

        $this->assertDatabaseHas('booking_payments', [
            'booking_id' => $this->don->id,
            'booking_surcharge_id' => $khoan->id,
            'kind' => BookingPayment::PHU_THU,
            'amount' => 1_200_000,
            'recorded_by' => $this->dieuHanh->id,
        ]);
    }

    /** Hoàn cho khách cũng vào sổ, chỉ khác chiều. */
    public function test_hoan_cho_khach_ghi_dong_nguoc_chieu(): void
    {
        $khoan = $this->taoKhoanDaDuyet('refund', 600_000);

        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/settle')->assertOk();

        $this->assertDatabaseHas('booking_payments', [
            'booking_surcharge_id' => $khoan->id,
            'kind' => BookingPayment::PHU_THU_HOAN,
            'amount' => 600_000,
        ]);
    }

    /** Khoản hoàn không cần khách đồng ý: không ai phản đối việc được trả lại tiền. */
    public function test_khoan_hoan_khong_can_khach_dong_y(): void
    {
        $khoan = $this->taoKhoanDaDuyet('refund', 600_000);

        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/settle')->assertOk();

        $this->assertSame(SurchargeStatus::Paid, $khoan->fresh()->status);
    }

    /**
     * Chưa nói với khách thì chưa thu.
     *
     * Người ở hiện trường đang mệt và đang bực; một khoản thu không ai giải thích là thứ sinh ra
     * khiếu nại, và lúc đó không có gì chứng minh đã từng giải thích.
     */
    public function test_chua_ghi_nhan_khach_dong_y_thi_chua_thu_duoc(): void
    {
        $khoan = $this->taoKhoanDaDuyet();

        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/settle')
            ->assertStatus(422);

        $this->assertSame(SurchargeStatus::Approved, $khoan->fresh()->status);
        $this->assertDatabaseMissing('booking_payments', ['booking_surcharge_id' => $khoan->id]);
    }

    /** Chưa duyệt thì chưa thu: duyệt là bước nói rằng con số đã chốt. */
    public function test_khoan_chua_duyet_thi_khong_thu_duoc(): void
    {
        $sc = $this->taoSuCo();

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/incidents/' . $sc->id . '/resolve', [
            'resolution' => 'Doi sang chuong trinh trong bo, o them mot dem tai khach san cu.',
            'who_bears' => 'customer',
            'charges' => [[
                'booking_id' => $this->don->id,
                'kind' => 'surcharge',
                'amount' => 1_200_000,
                'reason' => 'Mot dem phong doi.',
            ]],
        ])->assertOk();

        $khoan = BookingSurcharge::query()->firstOrFail();

        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/consent')->assertOk();
        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/settle')->assertStatus(422);

        $this->assertDatabaseMissing('booking_payments', ['booking_surcharge_id' => $khoan->id]);
    }

    /** Bấm hai lần thì lần sau bị từ chối, sổ không có hai dòng cho một lần thu. */
    public function test_thu_hai_lan_thi_lan_sau_bi_tu_choi(): void
    {
        $khoan = $this->taoKhoanDaDuyet();

        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/consent')->assertOk();
        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/settle')->assertOk();
        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/settle')->assertStatus(422);

        $this->assertSame(
            1,
            BookingPayment::query()->where('booking_surcharge_id', $khoan->id)->count(),
        );
    }

    // --- Khách phải thấy được khoản của mình ----------------------------------------------

    public function test_khach_thay_khoan_da_duyet_trong_don_cua_minh(): void
    {
        $khoan = $this->taoKhoanDaDuyet();

        $this->getJson('/api/bookings/' . $this->don->public_token)
            ->assertOk()
            ->assertJsonPath('data.surcharges.0.id', $khoan->id)
            ->assertJsonPath('data.surcharges.0.kind_label', 'Khách trả thêm')
            ->assertJsonPath('data.surcharges.0.reason', 'Mot dem phong doi va hai bua an phat sinh.');
    }

    /**
     * Cửa thứ hai vào cùng một đơn: trang hồ sơ của khách đã đăng nhập.
     *
     * Bài trên đi qua `show()` (tra cứu bằng mã), bài này đi qua `myBookings()`. Hai hàm khác
     * nhau, và màn hình khách thực sự dùng là hàm thứ hai — thử một cửa rồi tin cả hai cửa đều
     * đúng chính là kiểu lỗi dự án này đã gặp bảy lần.
     */
    public function test_khach_dang_nhap_cung_thay_khoan_trong_danh_sach_don(): void
    {
        $khach = User::create([
            'name' => 'Khach dang nhap',
            'email' => Str::random(8) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->don->forceFill(['customer_id' => $khach->getKey()])->save();

        $khoan = $this->taoKhoanDaDuyet();

        Sanctum::actingAs($khach);

        $this->getJson('/api/my-bookings')
            ->assertOk()
            ->assertJsonPath('data.0.surcharges.0.id', $khoan->id)
            ->assertJsonPath('data.0.surcharges.0.kind_label', 'Khách trả thêm');
    }

    /**
     * Khoản chờ duyệt thì chưa hiện.
     *
     * Con số điều hành còn đang cân nhắc mà đã hiện ra thì thành một mức tiền đã nói với khách -
     * và khách sẽ nhớ đúng con số đầu tiên họ đọc được, kể cả khi về sau nó đổi hoặc bị miễn.
     */
    public function test_khoan_cho_duyet_chua_hien_cho_khach(): void
    {
        $sc = $this->taoSuCo();

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/incidents/' . $sc->id . '/resolve', [
            'resolution' => 'Doi sang chuong trinh trong bo, dang can nhac muc thu.',
            'who_bears' => 'customer',
            'charges' => [[
                'booking_id' => $this->don->id,
                'kind' => 'surcharge',
                'amount' => 1_200_000,
                'reason' => 'Mot dem phong doi.',
            ]],
        ])->assertOk();

        $this->getJson('/api/bookings/' . $this->don->public_token)
            ->assertOk()
            ->assertJsonCount(0, 'data.surcharges');
    }

    /**
     * Tiền phụ thu KHÔNG được coi là tiền đã trả cho tour.
     *
     * Đây là cái bẫy mà việc ghi phụ thu vào sổ tạo ra. `paidAmount()` phân nhánh theo "sổ đã có
     * dòng chưa"; nếu câu hỏi ấy đếm cả dòng phụ thu thì một đơn lẻ đã trả đủ qua cổng bỗng rơi
     * vào nhánh sổ, cộng các loại tiền giá tour ra 0, và báo đã thu 0 đồng.
     */
    public function test_dong_phu_thu_khong_lam_hong_so_da_thu_cua_don(): void
    {
        $khoan = $this->taoKhoanDaDuyet();

        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/consent')->assertOk();
        $this->putJson('/api/admin/surcharges/' . $khoan->id . '/settle')->assertOk();

        $bao = app(\App\Services\CancellationPolicyService::class)
            ->quote($this->don->fresh(), $this->chuyen);

        $this->assertSame(
            4_000_000.0,
            $bao['paid_amount'],
            'Đơn đã trả đủ 4 triệu qua cổng; một triệu hai phụ thu không đổi con số đó.',
        );
    }
}
