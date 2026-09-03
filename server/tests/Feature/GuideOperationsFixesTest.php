<?php

namespace Tests\Feature;

use App\Enums\PassengerCheckinStatus;
use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Models\Booking;
use App\Models\CheckpointPhoto;
use App\Models\PassengerCheckin;
use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\VNPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Các luật vận hành vừa được vá: phân công, điểm danh, thông báo, và lượt trả tiền hỏng.
 *
 * Điểm chung của nhóm này: luật đã tồn tại ở một đường ghi và thiếu ở đường ghi kia — đúng khuôn
 * lỗi mà chú thích của `ScheduleGuideService::lyDoChan()` cảnh báo, và lần này nó xảy ra thật.
 */
class GuideOperationsFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.vnpay.hash_secret', 'secret-cho-test');

        $this->dieuHanh = $this->taoNguoi('admin');

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'adult_price' => 2_000_000,
            'child_price' => 1_400_000,
            'infant_price' => 0,
        ]);
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

    private function taoChuyen($start, array $ghiDe = []): TourSchedule
    {
        $start = \Illuminate\Support\Carbon::parse($start);

        return TourSchedule::create(array_merge([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 45,
            'min_people' => 2,
            'booked_people' => 0,
        ], $ghiDe));
    }

    /** @param array<int, array<string, mixed>> $schedules */
    private function luuTour(array $schedules): \Illuminate\Testing\TestResponse
    {
        return $this->putJson('/api/admin/tours/' . $this->tour->id, [
            'title' => $this->tour->title,
            'adult_price' => 2_000_000,
            'child_price' => 1_400_000,
            'infant_price' => 0,
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'start_location' => 'Ha Noi',
            'schedules' => $schedules,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Phân công hướng dẫn viên
    // ─────────────────────────────────────────────────────────────────────────────────────

    /**
     * Form sửa tour phải chịu đúng luật trùng lịch mà đường phân công lẻ đang chặn.
     *
     * Trước đây `validateScheduleGuideAssignments()` chỉ được gọi ở `store()`, còn `update()` ghi
     * thẳng vào bảng nối. Nghĩa là form sửa tour là một cửa sau: một người được gán cho hai chuyến
     * chồng ngày, và không có gì báo lại.
     */
    public function test_form_sua_tour_chan_phan_cong_trung_lich(): void
    {
        $guide = $this->taoNguoi('guide');

        $chuyenA = $this->taoChuyen(now()->addDays(20));
        $chuyenB = $this->taoChuyen(now()->addDays(21)); // Tour 3 ngày nên hai chuyến chồng nhau.

        $chuyenA->guides()->sync([$guide->id]);

        Sanctum::actingAs($this->dieuHanh);

        $this->luuTour([
            [
                'id' => $chuyenA->id,
                'start_date' => $chuyenA->start_date->format('Y-m-d H:i:s'),
                'max_people' => 45,
                'guide_ids' => [$guide->id],
            ],
            [
                'id' => $chuyenB->id,
                'start_date' => $chuyenB->start_date->format('Y-m-d H:i:s'),
                'max_people' => 45,
                'guide_ids' => [$guide->id],
            ],
        ])->assertStatus(422);

        $this->assertFalse(
            $chuyenB->fresh()->hasGuide($guide->id),
            'Một người không đứng ở hai đoàn cùng lúc, kể cả khi gán qua form sửa tour.',
        );
    }

    /** Hai chuyến cách xa nhau thì vẫn gán cùng một người được. */
    public function test_form_sua_tour_van_gan_duoc_khi_khong_trung_lich(): void
    {
        $guide = $this->taoNguoi('guide');

        $chuyenA = $this->taoChuyen(now()->addDays(20));
        $chuyenB = $this->taoChuyen(now()->addDays(40));

        Sanctum::actingAs($this->dieuHanh);

        $this->luuTour([
            [
                'id' => $chuyenA->id,
                'start_date' => $chuyenA->start_date->format('Y-m-d H:i:s'),
                'max_people' => 45,
                'guide_ids' => [$guide->id],
            ],
            [
                'id' => $chuyenB->id,
                'start_date' => $chuyenB->start_date->format('Y-m-d H:i:s'),
                'max_people' => 45,
                'guide_ids' => [$guide->id],
            ],
        ])->assertOk();

        $this->assertTrue($chuyenA->fresh()->hasGuide($guide->id));
        $this->assertTrue($chuyenB->fresh()->hasGuide($guide->id));
    }

    /**
     * Không xóa được hướng dẫn viên đang phụ trách một đoàn đã chốt hoặc đang đi.
     *
     * `User` xóa mềm và quan hệ `guides()` chịu global scope của nó, nên xóa một người sẽ âm thầm
     * gỡ họ khỏi mọi chuyến: hàng pivot còn đó nhưng không truy vấn nào nhìn thấy. Đoàn đang trên
     * đường bỗng không còn ai phụ trách trên hệ thống, và chính người đang đứng cùng đoàn mất quyền
     * điểm danh, báo sự cố, xin bàn giao.
     */
    public function test_khong_xoa_duoc_huong_dan_vien_dang_dan_doan(): void
    {
        $guide = $this->taoNguoi('guide');
        $chuyen = $this->taoChuyen(now()->subHours(2), ['status' => ScheduleStatus::InProgress->value]);
        $chuyen->guides()->sync([$guide->id]);

        Sanctum::actingAs($this->dieuHanh);

        $this->deleteJson('/api/admin/guides/' . $guide->id)->assertStatus(422);

        $this->assertTrue($chuyen->fresh()->hasGuide($guide->id));
    }

    /** Người không còn phụ trách chuyến nào đang chạy thì vẫn xóa được, và token bị thu hồi. */
    public function test_xoa_duoc_huong_dan_vien_ranh_va_thu_hoi_phien_dang_nhap(): void
    {
        $guide = $this->taoNguoi('guide');
        $guide->createToken('phien-cu');

        $this->assertSame(1, $guide->tokens()->count());

        Sanctum::actingAs($this->dieuHanh);

        $this->deleteJson('/api/admin/guides/' . $guide->id)->assertOk();

        $this->assertSame(0, $guide->tokens()->count());
        $this->assertNotNull($guide->fresh()->deleted_at);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Điểm danh
    // ─────────────────────────────────────────────────────────────────────────────────────

    /** @return array{0: TourSchedule, 1: User, 2: \App\Models\ItineraryCheckpoint, 3: \App\Models\BookingPassenger} */
    private function dungHienTruong(bool $batBuocAnh): array
    {
        $guide = $this->taoNguoi('guide');
        $chuyen = $this->taoChuyen(now()->subHours(2), ['status' => ScheduleStatus::InProgress->value]);
        $chuyen->guides()->sync([$guide->id]);

        /** @var TourItinerary $lichTrinh */
        $lichTrinh = $this->tour->itineraries()->create([
            'day_number' => 1, 'title' => 'Ngay 1', 'content' => 'Noi dung ngay mot',
        ]);

        $diem = $lichTrinh->checkpoints()->create([
            'name' => 'Tram dung nghi',
            'sequence' => 1,
            'is_required_photo' => $batBuocAnh,
        ]);

        $don = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Khach',
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $chuyen->start_date,
            'guests' => 1, 'seats' => 1, 'adult_count' => 1,
            'total_amount' => 2_000_000,
            'status' => 'confirmed', 'confirmed_at' => now(),
        ]);

        $hanhKhach = $don->passengers()->create(['name' => 'Nguyen Van A', 'type' => 'adult']);

        return [$chuyen, $guide, $diem, $hanhKhach];
    }

    /**
     * Điểm dừng bắt buộc ảnh thì chốt xong cả điểm mà chưa có ảnh là không ghi được.
     *
     * Luật này (tài liệu 04 §5.3) đã có sẵn ở `AttendanceService::assertCheckpointCompletable()`
     * kèm bài kiểm thử xanh, nhưng **không đường ghi nào gọi nó** — một luật có mã, có test, và
     * không có hiệu lực.
     */
    public function test_diem_dung_bat_buoc_anh_thi_chua_co_anh_khong_chot_duoc(): void
    {
        [$chuyen, $guide, $diem, $hanhKhach] = $this->dungHienTruong(batBuocAnh: true);

        Sanctum::actingAs($guide);

        $this->putJson("/api/guide/schedules/{$chuyen->id}/checkpoints/{$diem->id}/attendance", [
            'checkins' => [['booking_passenger_id' => $hanhKhach->id, 'status' => 'present']],
        ])->assertStatus(422);

        $this->assertSame(
            0,
            PassengerCheckin::query()->where('itinerary_checkpoint_id', $diem->id)->count(),
            'Cả lô phải quay lại, không để trạng thái nửa vời.',
        );
    }

    /** Tải ảnh lên rồi thì ghi được bình thường. */
    public function test_co_anh_roi_thi_chot_duoc_diem_dung(): void
    {
        [$chuyen, $guide, $diem, $hanhKhach] = $this->dungHienTruong(batBuocAnh: true);

        CheckpointPhoto::create([
            'tour_schedule_id' => $chuyen->id,
            'tour_itinerary_id' => $diem->tour_itinerary_id,
            'itinerary_checkpoint_id' => $diem->id,
            'guide_id' => $guide->id,
            'image_path' => 'https://example.com/anh.jpg',
            'captured_at' => now(),
        ]);

        Sanctum::actingAs($guide);

        $this->putJson("/api/guide/schedules/{$chuyen->id}/checkpoints/{$diem->id}/attendance", [
            'checkins' => [['booking_passenger_id' => $hanhKhach->id, 'status' => 'present']],
        ])->assertOk();
    }

    /** Điểm dừng không bắt buộc ảnh thì không đòi gì cả. */
    public function test_diem_dung_khong_bat_buoc_anh_thi_ghi_binh_thuong(): void
    {
        [$chuyen, $guide, $diem, $hanhKhach] = $this->dungHienTruong(batBuocAnh: false);

        Sanctum::actingAs($guide);

        $this->putJson("/api/guide/schedules/{$chuyen->id}/checkpoints/{$diem->id}/attendance", [
            'checkins' => [['booking_passenger_id' => $hanhKhach->id, 'status' => 'present']],
        ])->assertOk();
    }

    /**
     * Thông báo khách vắng mặt phải đọc được trong hộp thư của điều hành.
     *
     * Lớp thông báo cũ ghi xuống các khóa `type`/`message`, trong khi màn hình đọc
     * `kind`/`title`/`body`/`url`. Bản ghi vẫn được tạo nhưng hiện ra một dòng trắng: có thông báo,
     * không có chữ nào, bấm vào không đi đâu.
     */
    public function test_thong_bao_khach_vang_mat_co_tieu_de_va_noi_dung(): void
    {
        [$chuyen, $guide, $diem, $hanhKhach] = $this->dungHienTruong(batBuocAnh: false);

        Sanctum::actingAs($guide);

        $this->putJson("/api/guide/schedules/{$chuyen->id}/checkpoints/{$diem->id}/attendance", [
            'checkins' => [[
                'booking_passenger_id' => $hanhKhach->id,
                'status' => PassengerCheckinStatus::Absent->value,
                'note' => 'Khach khong co mat, goi khong nghe may',
            ]],
        ])->assertOk();

        Sanctum::actingAs($this->dieuHanh);

        $hop = $this->getJson('/api/notifications')->assertOk()->json('data.notifications');

        $this->assertCount(1, $hop);
        $this->assertNotSame('', $hop[0]['title']);
        $this->assertNotSame('', $hop[0]['body']);
        $this->assertStringContainsString('Nguyen Van A', $hop[0]['body']);
        $this->assertNotNull($hop[0]['url']);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Lượt trả tiền hỏng
    // ─────────────────────────────────────────────────────────────────────────────────────

    /**
     * Trả tiền thất bại thì đơn giữ nguyên chỗ tới hết hạn, không bị hủy ngay.
     *
     * "Thất bại" ở cổng phần lớn là chuyện khách sửa được trong một phút: sai OTP, thẻ không đủ số
     * dư, bấm Hủy để đổi sang thẻ khác. Hủy đơn ngay nghĩa là họ quay lại thì chỗ đã mất — và thời
     * hạn giữ chỗ sinh ra chính là để đựng khoảng thời gian đó.
     */
    public function test_tra_tien_that_bai_thi_don_van_giu_cho_toi_het_han(): void
    {
        Mail::fake();

        $chuyen = $this->taoChuyen(now()->addDays(20));

        $don = $this->postJson('/api/bookings', [
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Khach Doi The',
            'customer_email' => 'doithe@example.com',
            'adult_count' => 2,
            'accept_terms' => true,
        ])->assertStatus(201);

        $booking = Booking::query()->firstOrFail();
        $choTruoc = (int) $chuyen->fresh()->booked_people;

        $this->getJson('/api/vnpay/ipn?' . http_build_query(
            $this->vnpayQuayVe($booking, 4_000_000, thanhCong: false),
        ))->assertOk();

        $daSua = $booking->fresh();

        $this->assertSame('pending', $daSua->status, 'Đơn phải còn sống để khách thử lại.');
        $this->assertNotNull($daSua->expires_at);
        $this->assertSame(
            $choTruoc,
            (int) $chuyen->fresh()->booked_people,
            'Chỗ vẫn được giữ cho tới khi hết hạn thanh toán.',
        );
    }

    /** Hết hạn thì tác vụ nền vẫn dọn đúng như cũ — không có đơn nào nằm lại vĩnh viễn. */
    public function test_het_han_thi_don_that_bai_van_duoc_don(): void
    {
        Mail::fake();

        $chuyen = $this->taoChuyen(now()->addDays(20));

        $this->postJson('/api/bookings', [
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Khach Bo Cuoc',
            'customer_email' => 'bocuoc@example.com',
            'adult_count' => 2,
            'accept_terms' => true,
        ])->assertStatus(201);

        $booking = Booking::query()->firstOrFail();

        $this->getJson('/api/vnpay/ipn?' . http_build_query(
            $this->vnpayQuayVe($booking, 4_000_000, thanhCong: false),
        ))->assertOk();

        $booking->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->artisan('bookings:release-expired')->assertSuccessful();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(0, (int) $chuyen->fresh()->booked_people);
    }

    /** Dựng lượt VNPay quay về, ký đúng như cổng thật ký. */
    private function vnpayQuayVe(Booking $booking, float $soTien, bool $thanhCong = true): array
    {
        $params = [
            'vnp_Amount' => (int) round($soTien * 100),
            'vnp_BankCode' => 'NCB',
            'vnp_ResponseCode' => $thanhCong ? '00' : '24',
            'vnp_TransactionNo' => (string) random_int(10000000, 99999999),
            'vnp_TransactionStatus' => $thanhCong ? '00' : '02',
            'vnp_TxnRef' => app(VNPayService::class)->txnRef($booking),
        ];

        ksort($params);
        $hashData = collect($params)
            ->map(fn ($v, $k) => urlencode($k) . '=' . urlencode($v))
            ->implode('&');

        $params['vnp_SecureHash'] = hash_hmac('sha512', $hashData, 'secret-cho-test');

        return $params;
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Mức khách tối thiểu
    // ─────────────────────────────────────────────────────────────────────────────────────

    /**
     * `min_people` đếm GHẾ, không đếm người — cùng thước với `max_people`.
     *
     * `booked_people` cộng lên theo số ghế (em bé đi cùng bố mẹ không chiếm chỗ), nên đếm `guests`
     * ở đầu kia là đo hai đầu của một trục bằng hai cái thước. Chuyến `min_people = 4` bán được 2
     * người lớn kèm 2 em bé sẽ tự chốt chạy, trong khi chỉ có 2 suất thực sự bán được.
     */
    public function test_min_people_dem_ghe_khong_dem_em_be(): void
    {
        Mail::fake();

        $chuyen = $this->taoChuyen(now()->addDays(5), [
            'booking_deadline' => now()->subMinutes(5),
            'min_people' => 4,
            'booked_people' => 2,
        ]);

        Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Gia Dinh',
            'customer_email' => 'giadinh@example.com',
            'departure_date' => $chuyen->start_date,
            'guests' => 4,
            'seats' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 2,
            'total_amount' => 4_000_000,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'paid_at' => now(),
        ]);

        $this->artisan('schedules:confirm-ready')->assertSuccessful();

        $this->assertNotSame(
            ScheduleStatus::Confirmed,
            $chuyen->fresh()->status,
            'Hai người lớn và hai em bé chỉ là hai suất bán được, chưa đủ mức tối thiểu bốn.',
        );
    }
}
