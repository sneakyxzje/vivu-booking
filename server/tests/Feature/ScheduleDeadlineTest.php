<?php

namespace Tests\Feature;

use App\Enums\ScheduleAuditAction;
use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Mail\ScheduleDeadlineChangedMail;
use App\Models\Booking;
use App\Models\ScheduleAuditLog;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Notifications\Alert;
use App\Services\ScheduleDeadlineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Dời hạn chốt danh sách của một chuyến khởi hành.
 *
 * Hạn chốt là cái vạch chia trước và sau việc gửi danh sách cho nhà cung cấp. Dịch cái vạch ấy
 * đổi cùng lúc năm thứ, nên phải để lại vết và phải nói trước cho người bấm biết.
 *
 * Điều quan trọng nhất bộ test này giữ: kéo vạch KHÔNG tính lại các đơn đã xử lý.
 *
 * Xem docs/nghiep-vu/16-sua-han-chot.md.
 */
class ScheduleDeadlineTest extends TestCase
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
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 2,
            'adult_price' => 2_000_000,
        ]);

        // Khởi hành sau 20 ngày, hạn chốt sau 17 ngày.
        $this->chuyen = $this->taoChuyen();
    }

    private function taoChuyen(array $ghiDe = []): TourSchedule
    {
        $start = now()->addDays(20);

        return TourSchedule::create(array_merge([
            'tour_id' => $this->tour->id,
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

    private function service(): ScheduleDeadlineService
    {
        return app(ScheduleDeadlineService::class);
    }

    // --- Nhật ký ------------------------------------------------------------------------

    public function test_doi_han_chot_thi_ghi_lai_ngay_cu_va_ngay_moi(): void
    {
        $cu = $this->chuyen->booking_deadline;
        $moi = $cu->copy()->addDays(2);

        Sanctum::actingAs($this->dieuHanh);

        $this->patchJson('/api/admin/schedules/' . $this->chuyen->id . '/deadline', [
            'booking_deadline' => $moi->toDateTimeString(),
            'reason' => 'Khach san cho them 2 phong.',
        ])->assertOk();

        $log = ScheduleAuditLog::query()
            ->where('tour_schedule_id', $this->chuyen->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'Đổi hạn chốt phải để lại vết.');
        $this->assertSame(ScheduleAuditAction::DeadlineChanged, $log->action);
        $this->assertSame($this->dieuHanh->id, $log->actor_id);
        $this->assertSame('admin', $log->actor_role);
        $this->assertSame('Khach san cho them 2 phong.', $log->reason);

        $this->assertTrue(
            Carbon::parse($log->old_values['booking_deadline'])->equalTo($cu),
            'Nhật ký phải giữ đúng ngày trước khi sửa.',
        );
        $this->assertTrue(
            Carbon::parse($log->new_values['booking_deadline'])->equalTo($moi),
        );

        $this->assertTrue($this->chuyen->fresh()->booking_deadline->equalTo($moi));
    }

    public function test_luu_lai_dung_ngay_cu_thi_khong_ghi_nhat_ky_rong(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->patchJson('/api/admin/schedules/' . $this->chuyen->id . '/deadline', [
            'booking_deadline' => $this->chuyen->booking_deadline->toDateTimeString(),
        ])->assertOk();

        $this->assertSame(
            0,
            ScheduleAuditLog::query()->where('tour_schedule_id', $this->chuyen->id)->count(),
            'Không đổi gì thì không được sinh dòng nhật ký nào.',
        );
    }

    /**
     * Không ghi lý do thì không đổi được hạn chốt.
     *
     * Nhật ký có đủ ai/lúc nào/từ đâu sang đâu mà thiếu *vì sao* thì ba tháng sau nó chỉ tố cáo
     * được một người chứ không giải thích được một quyết định. Luật nằm ở service nên cả hai
     * đường ghi cùng chịu.
     */
    public function test_khong_ghi_ly_do_thi_khong_doi_duoc_han_chot(): void
    {
        $cu = $this->chuyen->booking_deadline;

        Sanctum::actingAs($this->dieuHanh);

        $this->patchJson('/api/admin/schedules/' . $this->chuyen->id . '/deadline', [
            'booking_deadline' => $cu->copy()->addDay()->toDateTimeString(),
        ])->assertStatus(422);

        $this->assertTrue(
            $this->chuyen->fresh()->booking_deadline->equalTo($cu),
            'Từ chối vì thiếu lý do thì hạn chốt phải giữ nguyên.',
        );

        $this->assertSame(
            0,
            ScheduleAuditLog::query()->where('tour_schedule_id', $this->chuyen->id)->count(),
        );
    }

    /** Lý do cụt ngủn cũng không tính: "ok" không trả lời được câu hỏi nào. */
    public function test_ly_do_qua_ngan_thi_khong_duoc_nhan(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->patchJson('/api/admin/schedules/' . $this->chuyen->id . '/deadline', [
            'booking_deadline' => $this->chuyen->booking_deadline->copy()->addDay()->toDateTimeString(),
            'reason' => '  ok  ',
        ])->assertStatus(422);
    }

    /** Đường ghi thứ hai cũng không lách được: form sửa tour đổi hạn chốt thì cũng phải khai lý do. */
    public function test_form_sua_tour_doi_han_chot_ma_khong_khai_ly_do_thi_bi_tu_choi(): void
    {
        $cu = $this->chuyen->booking_deadline;

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/tours/' . $this->tour->id, [
            'title' => $this->tour->title,
            'adult_price' => 2_000_000,
            'child_price' => 1_000_000,
            'infant_price' => 0,
            'number_of_days' => 2,
            'number_of_nights' => 1,
            'start_location' => 'Ha Noi',
            'schedules' => [[
                'id' => $this->chuyen->id,
                'start_date' => $this->chuyen->start_date->toDateTimeString(),
                'max_people' => 20,
                'booking_deadline' => $cu->copy()->subDay()->toDateTimeString(),
            ]],
        ])->assertStatus(422);

        $this->assertTrue($this->chuyen->fresh()->booking_deadline->equalTo($cu));
    }

    /**
     * Hạn chốt có hai đường ghi: form sửa tour và endpoint sửa nhanh.
     *
     * Luật nằm ở một đường mà thiếu ở đường kia chính là khuôn của mấy lỗi gần đây, nên bài này
     * giữ đường còn lại.
     */
    public function test_sua_tu_form_sua_tour_cung_duoc_ghi_nhat_ky(): void
    {
        $moi = $this->chuyen->booking_deadline->copy()->subDays(2);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/tours/' . $this->tour->id, [
            'title' => $this->tour->title,
            'adult_price' => 2_000_000,
            'child_price' => 1_000_000,
            'infant_price' => 0,
            'number_of_days' => 2,
            'number_of_nights' => 1,
            'start_location' => 'Ha Noi',
            'schedules' => [[
                'id' => $this->chuyen->id,
                'start_date' => $this->chuyen->start_date->toDateTimeString(),
                'max_people' => 20,
                'booking_deadline' => $moi->toDateTimeString(),
                'booking_deadline_reason' => 'Nha xe doi chot som.',
            ]],
        ])->assertOk();

        $log = ScheduleAuditLog::query()
            ->where('tour_schedule_id', $this->chuyen->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'Sửa hạn chốt từ form tour cũng phải để lại vết.');
        $this->assertSame('Nha xe doi chot som.', $log->reason);
        $this->assertTrue($this->chuyen->fresh()->booking_deadline->equalTo($moi));
    }

    /**
     * Biểu mẫu tour không gửi hạn chốt thì hạn chốt phải còn nguyên.
     *
     * Lỗi cũ: thiếu trường thì controller tự điền "khởi hành trừ ba ngày" rồi ghi đè. Nên một lần
     * lưu tour chỉ để sửa tiêu đề cũng xóa mất mốc điều hành đã thương lượng với nhà cung cấp, âm
     * thầm và không có gì báo lại - trong khi mốc ấy điều khiển năm quy tắc khác nhau.
     */
    public function test_form_sua_tour_khong_gui_han_chot_thi_giu_nguyen(): void
    {
        $rieng = $this->chuyen->start_date->copy()->subDays(10);
        $this->chuyen->forceFill(['booking_deadline' => $rieng])->save();

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/tours/' . $this->tour->id, [
            'title' => 'Ten tour vua doi',
            'adult_price' => 2_000_000,
            'child_price' => 1_000_000,
            'infant_price' => 0,
            'number_of_days' => 2,
            'number_of_nights' => 1,
            'start_location' => 'Ha Noi',
            'schedules' => [[
                'id' => $this->chuyen->id,
                'start_date' => $this->chuyen->start_date->toDateTimeString(),
                'max_people' => 20,
            ]],
        ])->assertOk();

        $this->assertTrue(
            $this->chuyen->fresh()->booking_deadline->equalTo($rieng),
            'Không gửi hạn chốt thì không được đụng vào hạn chốt.',
        );

        $this->assertSame(
            0,
            ScheduleAuditLog::query()->where('tour_schedule_id', $this->chuyen->id)->count(),
            'Không đổi gì thì cũng không có dòng nhật ký nào.',
        );
    }

    // --- Luật chặn ----------------------------------------------------------------------

    public function test_chuyen_dang_chay_thi_khong_sua_duoc_han_chot(): void
    {
        $dangChay = $this->taoChuyen([
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'booking_deadline' => now()->subDays(4),
        ]);

        Sanctum::actingAs($this->dieuHanh);

        $this->patchJson('/api/admin/schedules/' . $dangChay->id . '/deadline', [
            'booking_deadline' => now()->addDay()->toDateTimeString(),
        ])->assertStatus(422);

        $this->assertTrue(
            $dangChay->fresh()->booking_deadline->equalTo($dangChay->booking_deadline),
        );
    }

    public function test_han_chot_khong_duoc_roi_vao_sau_ngay_khoi_hanh(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->patchJson('/api/admin/schedules/' . $this->chuyen->id . '/deadline', [
            'booking_deadline' => $this->chuyen->start_date->copy()->addHour()->toDateTimeString(),
        ])->assertStatus(422);
    }

    // --- Điều quan trọng nhất: quá khứ không bị tính lại ---------------------------------

    /**
     * Chị Lan hủy khi đã qua hạn chốt nên mất chỗ. Sau đó điều hành xin thêm được suất và dời
     * hạn chốt về sau ngày chị hủy.
     *
     * Kết quả của chị Lan phải giữ nguyên, vì lúc chị hủy thì phòng đã chốt thật. Kết quả ấy được
     * ghi cứng vào đơn chứ không phải phép tính chạy lại mỗi lần mở màn hình - đây là bài giữ
     * đúng điều đó.
     */
    public function test_doi_han_chot_khong_tinh_lai_don_da_huy(): void
    {
        $chuyen = $this->taoChuyen([
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(3),
            'booking_deadline' => now()->subDay(),
            'status' => ScheduleStatus::Closed->value,
        ]);

        $don = $this->taoDon($chuyen);
        $soChoTruoc = (int) $chuyen->fresh()->booked_people;

        // Hủy sau hạn chốt: chỗ không về kho.
        app(\App\Services\BookingHoldService::class)->releaseHold($don, $chuyen->fresh());
        $don->forceFill(['status' => 'cancelled'])->save();

        $this->assertFalse((bool) $don->fresh()->seats_released);
        $this->assertSame($soChoTruoc, (int) $chuyen->fresh()->booked_people);

        // Dời hạn chốt về sau thời điểm chị Lan hủy.
        $this->service()->change($chuyen->fresh(), now()->addDay(), 'Xin them duoc suat.', $this->dieuHanh);

        $this->assertFalse(
            (bool) $don->fresh()->seats_released,
            'Đơn đã hủy phải giữ nguyên kết quả cũ, không được tính lại theo hạn chốt mới.',
        );
        $this->assertSame(
            $soChoTruoc,
            (int) $chuyen->fresh()->booked_people,
            'Chỗ đã chết không tự sống lại khi dời hạn chốt.',
        );
    }

    // --- Xem trước tác động --------------------------------------------------------------

    public function test_xem_truoc_nhac_chuyen_khong_tu_mo_ban_lai(): void
    {
        $chuyen = $this->taoChuyen([
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(3),
            'booking_deadline' => now()->subDay(),
            'status' => ScheduleStatus::Closed->value,
        ]);

        Sanctum::actingAs($this->dieuHanh);

        $response = $this->getJson(
            '/api/admin/schedules/' . $chuyen->id . '/deadline-impact'
            . '?booking_deadline=' . urlencode(now()->addDay()->toDateTimeString())
        )->assertOk();

        $impact = $response->json('data.impact');

        $this->assertSame('later', $impact['direction']);
        $this->assertTrue($impact['currently_past']);
        $this->assertFalse($impact['will_be_past']);
        $this->assertTrue($impact['needs_manual_reopen']);
        $this->assertTrue($impact['can_change']);

        $this->assertNotEmpty(array_filter(
            $impact['warnings'],
            fn (string $dong) => str_contains($dong, 'Mở bán'),
        ), 'Phải nhắc rằng chuyến không tự mở bán lại.');
    }

    public function test_xem_truoc_dem_dung_so_ghe_chet(): void
    {
        $chuyen = $this->taoChuyen([
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(3),
            'booking_deadline' => now()->subDay(),
            'status' => ScheduleStatus::Closed->value,
        ]);

        $don = $this->taoDon($chuyen, khach: 3);
        $don->forceFill(['status' => 'cancelled', 'seats_released' => false])->save();

        Sanctum::actingAs($this->dieuHanh);

        $impact = $this->getJson(
            '/api/admin/schedules/' . $chuyen->id . '/deadline-impact'
            . '?booking_deadline=' . urlencode(now()->addDay()->toDateTimeString())
        )->assertOk()->json('data.impact');

        $this->assertSame(1, $impact['held_seat_bookings']);
        $this->assertSame(3, $impact['held_seats']);

        $this->assertNotEmpty(array_filter(
            $impact['warnings'],
            fn (string $dong) => str_contains($dong, 'không tự trả về kho'),
        ));
    }

    public function test_xem_truoc_khi_rut_ngan_han_chot(): void
    {
        $this->taoDon($this->chuyen, khach: 4);

        Sanctum::actingAs($this->dieuHanh);

        $impact = $this->getJson(
            '/api/admin/schedules/' . $this->chuyen->id . '/deadline-impact'
            . '?booking_deadline=' . urlencode(now()->addHours(2)->toDateTimeString())
        )->assertOk()->json('data.impact');

        $this->assertSame('earlier', $impact['direction']);
        $this->assertTrue($impact['can_change']);
        $this->assertSame(1, $impact['manifest_bookings']);

        // Rút ngắn tước quyền của khách đang có, nên phải nói ra bao nhiêu đơn bị chạm tới.
        $this->assertNotEmpty(array_filter(
            $impact['warnings'],
            fn (string $dong) => str_contains($dong, 'không sửa được tên hành khách'),
        ));

        // Hai câu trấn an luôn phải có mặt, vì đây là hai điều người bấm hay lo nhất.
        $this->assertNotEmpty(array_filter(
            $impact['warnings'],
            fn (string $dong) => str_contains($dong, 'không tính lại'),
        ));
        $this->assertNotEmpty(array_filter(
            $impact['warnings'],
            fn (string $dong) => str_contains($dong, 'Số tiền hoàn của mọi đơn không đổi'),
        ));
    }

    // --- Thông báo ----------------------------------------------------------------------

    /**
     * Dời hạn chốt thì mọi khách của chuyến đều được báo.
     *
     * Rút hạn chốt là tước quyền của khách đang có: từ mốc mới họ không tự sửa được tên hành khách,
     * không xin đổi chuyến được, hủy thì mất chỗ. Làm việc ấy trong im lặng thì khách chỉ biết lúc
     * bấm không được, và lúc đó thứ họ nghĩ đầu tiên là hệ thống hỏng.
     *
     * Đơn đang giữ chỗ chưa trả tiền cũng nhận thư: quyền khai danh sách hành khách của họ đóng lại
     * theo đúng mốc này. Đơn đã hủy thì không - họ không còn đi nữa.
     */
    public function test_doi_han_chot_thi_bao_cho_moi_khach_cua_chuyen(): void
    {
        Mail::fake();

        $daTraTien = $this->taoDon($this->chuyen);
        $dangGiuCho = $this->taoDon($this->chuyen, 'pending');
        $this->taoDon($this->chuyen, 'cancelled');

        Sanctum::actingAs($this->dieuHanh);

        $this->patchJson('/api/admin/schedules/' . $this->chuyen->id . '/deadline', [
            'booking_deadline' => $this->chuyen->booking_deadline->copy()->subDays(2)->toDateTimeString(),
            'reason' => 'Nha xe chot ghe som hon mot ngay.',
        ])->assertOk();

        // Đúng hai thư: đơn đã hủy không nằm trong số đó.
        Mail::assertQueued(ScheduleDeadlineChangedMail::class, 2);

        Mail::assertQueued(
            ScheduleDeadlineChangedMail::class,
            fn (ScheduleDeadlineChangedMail $thu) => $thu->hasTo($daTraTien->customer_email),
        );
        Mail::assertQueued(
            ScheduleDeadlineChangedMail::class,
            fn (ScheduleDeadlineChangedMail $thu) => $thu->hasTo($dangGiuCho->customer_email),
        );
    }

    /**
     * Thư phải dựng được thật, không chỉ được xếp hàng.
     *
     * `Mail::fake()` không đụng tới tệp blade, nên một lỗi cú pháp trong đó đi lọt qua mọi bài trên
     * và chỉ lộ ra ở hộp thư của khách. Bài này dựng thư ra chuỗi và đọc lại hai câu bắt buộc phải
     * có: mốc mới, và lời trấn an rằng tiền hoàn không đổi.
     */
    public function test_thu_bao_dung_duoc_va_noi_dung_dung_huong(): void
    {
        $don = $this->taoDon($this->chuyen);

        $noiDung = (new ScheduleDeadlineChangedMail(
            $don,
            now()->addDays(17),
            now()->addDays(15),
            'Nha xe chot ghe som hon mot ngay.',
        ))->render();

        $this->assertStringContainsString('hạn chốt danh sách', $noiDung);
        $this->assertStringContainsString(now()->addDays(15)->format('d/m/Y'), $noiDung);
        $this->assertStringContainsString('Nha xe chot ghe som hon mot ngay.', $noiDung);

        // Rút ngắn thì phải nói thẳng là khách sắp mất quyền tự sửa, không nói vòng.
        $this->assertStringContainsString('không tự sửa được nữa', $noiDung);
        $this->assertStringContainsString('không đổi', $noiDung);
    }

    /** Người cầm danh sách đoàn đi gặp nhà cung cấp cũng phải biết mốc vừa dịch. */
    public function test_huong_dan_vien_cua_chuyen_cung_duoc_bao(): void
    {
        Mail::fake();

        $huongDanVien = User::create([
            'name' => 'Huong Dan Vien Test',
            'email' => 'hdv-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'guide',
            'status' => 'active',
        ]);

        $this->chuyen->guides()->attach($huongDanVien->id);

        Sanctum::actingAs($this->dieuHanh);

        $this->patchJson('/api/admin/schedules/' . $this->chuyen->id . '/deadline', [
            'booking_deadline' => $this->chuyen->booking_deadline->copy()->subDays(2)->toDateTimeString(),
            'reason' => 'Nha xe chot ghe som hon mot ngay.',
        ])->assertOk();

        $data = $huongDanVien->notifications()
            ->where('type', Alert::class)
            ->get()
            ->firstWhere(fn ($tb) => $tb->data['kind'] === Alert::HAN_CHOT_DOI)
            ?->data;

        $this->assertNotNull($data, 'Hướng dẫn viên phụ trách chuyến phải được báo.');
        $this->assertStringContainsString('Nha xe chot ghe som hon', $data['body']);
    }

    /**
     * Thao tác bị từ chối thì tuyệt đối không có thư nào bay đi.
     *
     * Thư báo một thay đổi không xảy ra là loại sai không đính chính được: nó đã nằm trong hộp thư
     * của khách rồi.
     */
    public function test_thao_tac_bi_tu_choi_thi_khong_ai_nhan_thu(): void
    {
        Mail::fake();

        $this->taoDon($this->chuyen);

        Sanctum::actingAs($this->dieuHanh);

        $this->patchJson('/api/admin/schedules/' . $this->chuyen->id . '/deadline', [
            'booking_deadline' => $this->chuyen->booking_deadline->copy()->subDay()->toDateTimeString(),
        ])->assertStatus(422);

        Mail::assertNothingQueued();
    }

    // --- Không đặt mốc vào quá khứ -------------------------------------------------------

    /**
     * Hạn chốt đặt vào hôm qua là tuyên bố một điều chưa từng đúng.
     *
     * Hôm qua chuyến vẫn bán chỗ, vẫn cho sửa tên, khách hủy vẫn được trả chỗ. Ghi một mốc như thế
     * vào cơ sở dữ liệu thì nhật ký mất khả năng dựng lại chuyện đã xảy ra - đúng thứ duy nhất nó
     * sinh ra để làm.
     */
    public function test_khong_dat_duoc_han_chot_vao_qua_khu(): void
    {
        $cu = $this->chuyen->booking_deadline;

        Sanctum::actingAs($this->dieuHanh);

        $this->patchJson('/api/admin/schedules/' . $this->chuyen->id . '/deadline', [
            'booking_deadline' => now()->subHour()->toDateTimeString(),
            'reason' => 'Nha xe bao chot som tu hom qua.',
        ])->assertStatus(422);

        $this->assertTrue($this->chuyen->fresh()->booking_deadline->equalTo($cu));
    }

    /** Xem trước phải nói trước, không để người dùng bấm lưu rồi mới biết bị chặn. */
    public function test_xem_truoc_bao_truoc_rang_moc_qua_khu_bi_chan(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $impact = $this->getJson(
            '/api/admin/schedules/' . $this->chuyen->id . '/deadline-impact'
            . '?booking_deadline=' . urlencode(now()->subHour()->toDateTimeString())
        )->assertOk()->json('data.impact');

        $this->assertFalse($impact['can_change']);
        $this->assertStringContainsString('quá khứ', $impact['blocked_reason']);
    }

    /**
     * Khóa danh sách ngay vẫn làm được, chỉ là phải nói đúng thứ mình làm.
     *
     * Nhà cung cấp chốt sớm hơn thỏa thuận là chuyện có thật, và lúc ấy điều hành cần khóa danh
     * sách ngay hôm nay. Luật chặn mốc quá khứ không được phép chặn luôn việc đó.
     */
    public function test_van_khoa_duoc_danh_sach_ngay_bang_moc_hien_tai(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->patchJson('/api/admin/schedules/' . $this->chuyen->id . '/deadline', [
            'booking_deadline' => now()->addMinute()->toDateTimeString(),
            'reason' => 'Khach san chot phong sang nay, khoa danh sach luon.',
        ])->assertOk();

        $this->assertTrue(
            $this->chuyen->fresh()->booking_deadline->lte(now()->addMinute()),
        );
    }
}
