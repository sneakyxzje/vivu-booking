<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * G03 - Khai danh sách hành khách sau khi đặt, bằng mã tra cứu.
 *
 * ## Vì sao đường này tồn tại
 *
 * Đặt tour không cần tài khoản, nhưng đường sửa hành khách trước đây nằm sau `role:customer`.
 * Nghĩa là **khách vãng lai đặt xong là vĩnh viễn không sửa được danh sách** - gõ nhầm một số
 * căn cước thì chịu. Đây là lỗ hổng, không phải tính năng thêm cho tiện.
 *
 * ## Điều bộ test này giữ
 *
 * Mở đường mới **không được nới luật cũ**. Quyền sửa vẫn do `PassengerPolicyService` quyết, và
 * ba mốc vẫn nguyên: trước hạn chốt khách tự sửa, sau hạn chốt chỉ điều hành, khởi hành rồi thì
 * khóa. Nếu một ngày nào đó đường công khai lách qua được, các bài dưới đây đỏ.
 */
class PublicPassengerDeclarationTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;
    private TourSchedule $chuyen;
    private Booking $don;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 3,
            'adult_price' => 2_000_000,
        ]);

        $this->chuyen = $this->taoChuyen(now()->addDays(20));
        $this->don = $this->taoDon();
    }

    private function taoChuyen($start, ScheduleStatus $status = ScheduleStatus::Open): TourSchedule
    {
        $start = Carbon::parse($start);

        return TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => $status->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 3,
        ]);
    }

    /** Đơn của khách vãng lai: không có customer_id, chỉ có mã tra cứu. */
    private function taoDon(): Booking
    {
        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $this->chuyen->id,
            'customer_id' => null,
            'customer_name' => 'Nguyễn Văn Đại Diện',
            'customer_email' => 'daidien@example.com',
            'customer_phone' => '0901234567',
            'departure_date' => $this->chuyen->start_date,
            'guests' => 3,
            'adult_count' => 2,
            'child_count' => 1,
            'infant_count' => 0,
            'total_amount' => 5_000_000,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);
    }

    private function duong(string $duoi = ''): string
    {
        return '/api/bookings/' . $this->don->public_token . '/passengers' . $duoi;
    }

    /** @return array<int, array<string, mixed>> */
    private function haiNguoiLonMotTre(): array
    {
        return [
            ['name' => 'Nguyễn Văn A', 'type' => 'adult', 'identity_number' => '001199001234', 'is_contact' => true],
            ['name' => 'Trần Thị B', 'type' => 'adult', 'identity_number' => '001199005678'],
            ['name' => 'Nguyễn Bé C', 'type' => 'child'],
        ];
    }

    // --- Khách vãng lai khai được ------------------------------------------------------------

    /**
     * Bài quan trọng nhất: **không đăng nhập** vẫn khai được danh sách.
     *
     * Đây chính là lỗ hổng cũ. Đơn dựng ở trên cố ý để `customer_id = null` - đúng hình dạng đơn
     * của khách vãng lai.
     */
    public function test_khach_vang_lai_khai_duoc_danh_sach_bang_ma_tra_cuu(): void
    {
        $this->putJson($this->duong(), ['passengers' => $this->haiNguoiLonMotTre(), 'customer_email' => 'daidien@example.com'])
            ->assertOk();

        $this->assertSame(3, $this->don->passengers()->count());
        $this->assertSame('Nguyễn Văn A', $this->don->passengers()->where('is_contact', true)->first()?->name);
    }

    public function test_doc_duoc_thong_tin_can_de_dung_bieu_mau(): void
    {
        $ds = $this->getJson($this->duong())->assertOk()->json('data');

        $this->assertTrue($ds['can_edit']);
        $this->assertSame(3, $ds['guests']);
        $this->assertSame(2, $ds['adult_count']);
        $this->assertSame(1, $ds['child_count']);
        // Hạn chốt phải trả về: đó là mốc khách mất quyền tự sửa, không để họ đoán.
        $this->assertNotNull($ds['deadline']);
        $this->assertSame('Nguyễn Văn Đại Diện', $ds['booking']['contact_name']);
    }

    public function test_ma_sai_thi_khong_lo_gi(): void
    {
        $this->getJson('/api/bookings/' . Str::uuid() . '/passengers')->assertStatus(404);
    }

    // --- Mã tra cứu thôi là chưa đủ để chạm dữ liệu cá nhân --------------------------------

    /**
     * Chỉ cầm mã tra cứu thì đọc được đơn, nhưng số giấy tờ hiện dạng che.
     *
     * Mã là chuỗi ngẫu nhiên khó đoán, nhưng nó đi trong thư — mà thư thì được chuyển tiếp, mở trên
     * máy dùng chung, còn lại trong lịch sử trình duyệt. Đủ để hỏi "đơn này thế nào", không đủ để
     * đọc căn cước của từng người trong đoàn.
     */
    public function test_khong_co_email_thi_so_giay_to_bi_che(): void
    {
        $this->putJson($this->duong(), [
            'passengers' => [[
                'name' => 'Nguyễn Văn A',
                'type' => 'adult',
                'identity_number' => '012345678901',
            ]],
            'customer_email' => 'daidien@example.com',
        ])->assertOk();

        $ds = $this->getJson($this->duong())->assertOk()->json('data');

        $this->assertTrue($ds['identity_masked']);
        $this->assertSame('••••••••8901', $ds['passengers'][0]['identity_number']);
    }

    /** Nhập đúng email đã đặt thì đọc được đầy đủ. */
    public function test_dung_email_thi_doc_duoc_day_du(): void
    {
        $this->putJson($this->duong(), [
            'passengers' => [[
                'name' => 'Nguyễn Văn A',
                'type' => 'adult',
                'identity_number' => '012345678901',
            ]],
            'customer_email' => 'daidien@example.com',
        ])->assertOk();

        $ds = $this->getJson($this->duong() . '?email=daidien@example.com')->assertOk()->json('data');

        $this->assertFalse($ds['identity_masked']);
        $this->assertSame('012345678901', $ds['passengers'][0]['identity_number']);
    }

    /** Cầm được đường dẫn nhưng không biết email thì không sửa được danh sách. */
    public function test_email_sai_thi_khong_sua_duoc_danh_sach(): void
    {
        $this->putJson($this->duong(), [
            'passengers' => $this->haiNguoiLonMotTre(),
            'customer_email' => 'nguoi-khac@example.com',
        ])->assertStatus(403);

        $this->assertSame(0, $this->don->passengers()->count());
    }

    /** Khai một phần vẫn lưu được — có tên ai thì điền tên người đó trước. */
    public function test_khai_mot_phan_van_luu_duoc(): void
    {
        $this->putJson($this->duong(), [
            'passengers' => [['name' => 'Nguyễn Văn A', 'type' => 'adult']],
            'customer_email' => 'daidien@example.com',
        ])->assertOk();

        $this->assertSame(1, $this->don->passengers()->count());
    }

    // --- Đường mới KHÔNG được nới luật cũ ----------------------------------------------------

    /**
     * Qua hạn chốt danh sách thì khách hết quyền tự sửa, kể cả đi đường công khai.
     *
     * Danh sách đã gửi khách sạn và nhà xe. Mở một lối vào mới mà quên luật này thì khách sửa
     * được thứ nhà cung cấp đã in ra.
     */
    public function test_qua_han_chot_thi_khach_khong_con_tu_sua_duoc(): void
    {
        $this->chuyen->update(['booking_deadline' => now()->subHour()]);

        $response = $this->putJson($this->duong(), ['passengers' => $this->haiNguoiLonMotTre(), 'customer_email' => 'daidien@example.com'])
            ->assertStatus(422);

        $this->assertStringContainsString('hạn chốt', $response->json('message'));
        $this->assertSame(0, $this->don->passengers()->count());

        // Và giao diện phải biết trước để không hiện nút lưu.
        $ds = $this->getJson($this->duong())->assertOk()->json('data');
        $this->assertFalse($ds['can_edit']);
        $this->assertNotNull($ds['locked_reason']);
    }

    /** Đoàn đã lên đường thì danh sách là dữ liệu điểm danh, không ai sửa. */
    public function test_chuyen_dang_chay_thi_khoa_han(): void
    {
        $this->chuyen->update([
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subDay(),
        ]);

        $this->putJson($this->duong(), ['passengers' => $this->haiNguoiLonMotTre(), 'customer_email' => 'daidien@example.com'])
            ->assertStatus(422);
    }

    /** Luật trùng số giấy tờ vẫn chạy trên đường công khai. */
    public function test_trung_so_giay_to_bi_tu_choi(): void
    {
        $this->putJson($this->duong(), [
            'passengers' => [
                ['name' => 'Nguyễn Văn A', 'type' => 'adult', 'identity_number' => '001199001234'],
                ['name' => 'Trần Thị B', 'type' => 'adult', 'identity_number' => '001199001234'],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, $this->don->passengers()->count());
    }

    // --- Đặt tour không còn bắt khai hành khách ----------------------------------------------

    /**
     * Đặt tour thành công mà không gửi kèm hành khách nào.
     *
     * Đây là cả điểm của việc tách: lúc bấm đặt, người đại diện thường chưa có số căn cước và
     * ngày sinh của cả nhóm.
     */
    public function test_dat_tour_khong_kem_hanh_khach_van_thanh_cong(): void
    {
        $chuyenMoi = $this->taoChuyen(now()->addDays(30));

        $response = $this->postJson('/api/bookings', [
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $chuyenMoi->id,
            'customer_name' => 'Khách Mới',
            'customer_email' => 'khachmoi@example.com',
            'customer_phone' => '0907654321',
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'accept_terms' => true,
        ])->assertStatus(201);

        $donMoi = Booking::query()->latest('id')->first();

        $this->assertSame(2, (int) $donMoi->guests);
        $this->assertSame(0, $donMoi->passengers()->count(), 'Chưa khai ai là đúng, không phải lỗi.');
        $this->assertNotEmpty($response->json('data.booking.public_token'));
    }
}
