<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * G06 - Thông tin hành khách và quyền sửa theo mốc thời gian.
 *
 * Câu số 3 của hội đồng. Luật ở docs/nghiep-vu/02-luong-dat-tour.md mục 3.1.
 *
 * Điểm cần khóa chặt: quyền sửa không phụ thuộc vai trò mà phụ thuộc thời điểm. Cùng một khách,
 * cùng một đơn, trước hạn chốt danh sách thì sửa được và sau đó thì không.
 */
class PassengerPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $khach;
    private User $dieuHanh;
    private TourSchedule $schedule;

    private function taoUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Test',
            'email' => $role . '-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->khach = $this->taoUser('customer');
        $this->dieuHanh = $this->taoUser('admin');

        $tour = Tour::factory()->create(['status' => 'active', 'number_of_days' => 2]);

        $this->schedule = TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(20),
            'end_date' => now()->addDays(22),
            'booking_deadline' => now()->addDays(17),
            'max_people' => 10,
            'min_people' => 2,
            'booked_people' => 2,
        ]);
    }

    /**
     * Dựng một đơn với cơ cấu khách nói rõ ràng.
     *
     * Nhận riêng số trẻ em và em bé thay vì mặc định gộp hết vào người lớn, vì danh sách khai
     * không được vượt số đã mua **theo từng loại** — loại khách quyết định giá vé. Bài nào khai
     * trẻ em thì đơn của nó phải thật sự có mua vé trẻ em, nếu không thì bài kiểm thử đang mô tả
     * một tình huống không tồn tại trên thực tế.
     */
    private function taoDon(int $guests = 2, int $child = 0, int $infant = 0): Booking
    {
        $adult = max(0, $guests - $child - $infant);

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->schedule->tour_id,
            'customer_id' => $this->khach->id,
            'tour_schedule_id' => $this->schedule->id,
            'customer_name' => $this->khach->name,
            'customer_email' => $this->khach->email,
            'departure_date' => $this->schedule->start_date,
            'guests' => $guests,
            'seats' => $adult + $child,
            'adult_count' => $adult,
            'child_count' => $child,
            'infant_count' => $infant,
            'total_amount' => 5_000_000 * $guests,
            'status' => 'confirmed',
            'paid_at' => now()->subDay(),
            'confirmed_at' => now()->subDay(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function danhSach(array $ghiDe = []): array
    {
        $mac = [
            [
                'name' => 'Nguyen Van An',
                'type' => 'adult',
                'gender' => 'male',
                'identity_number' => '001199001234',
                'id_type' => 'cccd',
                'is_contact' => true,
            ],
            [
                'name' => 'Tran Thi Binh',
                'type' => 'adult',
                'gender' => 'female',
                'identity_number' => '001199005678',
                'id_type' => 'cccd',
            ],
        ];

        return $ghiDe === [] ? $mac : $ghiDe;
    }

    // --- Quyền sửa theo mốc thời gian --------------------------------------------------

    public function test_truoc_han_chot_thi_khach_tu_sua_duoc(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->khach);

        $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => $this->danhSach(),
        ])->assertOk();

        $this->assertSame(2, $don->passengers()->count());
        $this->assertSame('Nguyen Van An', $don->passengers()->first()->name);
    }

    /**
     * Bài quan trọng nhất. Qua hạn chốt thì danh sách đã gửi khách sạn và nhà xe, khách sửa
     * nghĩa là hệ thống nói một đằng còn nhà cung cấp giữ một nẻo.
     */
    public function test_sau_han_chot_thi_khach_khong_sua_duoc_nua(): void
    {
        $don = $this->taoDon();
        $this->schedule->update(['booking_deadline' => now()->subHour()]);

        Sanctum::actingAs($this->khach);

        $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => $this->danhSach(),
        ])->assertStatus(422);

        $this->assertSame(0, $don->passengers()->count());
    }

    /** Cùng thời điểm đó điều hành vẫn sửa được, vì họ là người gọi báo nhà cung cấp. */
    public function test_sau_han_chot_thi_dieu_hanh_van_sua_duoc(): void
    {
        $don = $this->taoDon();
        $this->schedule->update(['booking_deadline' => now()->subHour()]);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson("/api/admin/bookings/{$don->id}/passengers", [
            'passengers' => $this->danhSach(),
        ])->assertOk();

        $this->assertSame(2, $don->passengers()->count());
    }

    /** Đoàn đã lên đường thì danh sách là dữ liệu đang dùng để điểm danh, không ai sửa. */
    public function test_chuyen_dang_chay_thi_ca_dieu_hanh_cung_khong_sua_duoc(): void
    {
        $don = $this->taoDon();
        $this->schedule->update([
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subHours(3),
            'end_date' => now()->addDay(),
            'booking_deadline' => now()->subDays(4),
        ]);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson("/api/admin/bookings/{$don->id}/passengers", [
            'passengers' => $this->danhSach(),
        ])->assertStatus(422);
    }

    public function test_man_hinh_biet_truoc_con_sua_duoc_hay_khong(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->khach);

        $this->getJson("/api/my-bookings/{$don->id}/passengers")
            ->assertOk()
            ->assertJsonPath('data.can_edit', true);

        $this->schedule->update(['booking_deadline' => now()->subHour()]);

        $response = $this->getJson("/api/my-bookings/{$don->id}/passengers")
            ->assertOk()
            ->assertJsonPath('data.can_edit', false);

        $this->assertNotEmpty($response->json('data.locked_reason'));
    }

    public function test_khach_khong_sua_duoc_don_cua_nguoi_khac(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->taoUser('customer'));

        $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => $this->danhSach(),
        ])->assertStatus(404);
    }

    // --- Quy tắc kiểm tra ---------------------------------------------------------------

    public function test_trung_so_giay_to_trong_cung_don_bi_tu_choi(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->khach);

        $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => [
                ['name' => 'Nguyen Van An', 'type' => 'adult', 'identity_number' => '001199001234'],
                ['name' => 'Tran Thi Binh', 'type' => 'adult', 'identity_number' => '001199001234'],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, $don->passengers()->count());
    }

    /** Bỏ trống số giấy tờ thì không tính là trùng, vì nhiều khách chưa hỏi kịp thông tin. */
    public function test_hai_nguoi_cung_bo_trong_giay_to_thi_khong_bi_coi_la_trung(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->khach);

        $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => [
                ['name' => 'Nguyen Van An', 'type' => 'adult'],
                ['name' => 'Tran Thi Binh', 'type' => 'adult'],
            ],
        ])->assertOk();
    }

    /**
     * Loại khách quyết định giá vé, nên khai một người lớn thành trẻ em là trả thiếu tiền.
     */
    public function test_ngay_sinh_khong_khop_loai_khach_thi_bi_tu_choi(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->khach);

        $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => [
                [
                    'name' => 'Nguyen Van An',
                    'type' => 'child',
                    'date_of_birth' => now()->subYears(30)->toDateString(),
                ],
            ],
        ])->assertStatus(422);
    }

    /** Tính tuổi tại NGÀY KHỞI HÀNH, không phải hôm nay. */
    public function test_tuoi_tinh_theo_ngay_khoi_hanh_chu_khong_phai_hom_nay(): void
    {
        $don = $this->taoDon(1);
        Sanctum::actingAs($this->khach);

        // Hôm nay bé còn 11 tuổi, nhưng tới ngày khởi hành thì đã tròn 12.
        $sinhNhat = now()->addDays(5)->subYears(12);

        $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => [
                [
                    'name' => 'Le Minh Cuong',
                    'type' => 'adult',
                    'date_of_birth' => $sinhNhat->toDateString(),
                ],
            ],
        ])->assertOk();

        // Khai là trẻ em thì sai, vì tới lúc đi đã đủ tuổi người lớn.
        $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => [
                [
                    'name' => 'Le Minh Cuong',
                    'type' => 'child',
                    'date_of_birth' => $sinhNhat->toDateString(),
                ],
            ],
        ])->assertStatus(422);
    }

    public function test_khong_co_ngay_sinh_thi_bo_qua_luat_tuoi(): void
    {
        // Đơn mua đúng một vé trẻ em, vì bài này khai một trẻ em.
        $don = $this->taoDon(guests: 1, child: 1);
        Sanctum::actingAs($this->khach);

        $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => [['name' => 'Nguyen Van An', 'type' => 'child']],
        ])->assertOk();
    }

    // --- G05: cảnh báo khai thiếu -------------------------------------------------------

    /**
     * Trẻ em và em bé không có giấy tờ riêng, nên không được tính vào cảnh báo thiếu giấy tờ.
     *
     * Đếm cả nhóm ấy thì mọi đơn có trẻ con đều đội một dòng đỏ không bao giờ tắt được. Người dùng
     * học cách bỏ qua cảnh báo, và tới lúc một người lớn thiếu giấy tờ thật thì dòng ấy cũng bị bỏ
     * qua nốt.
     */
    public function test_tre_em_va_em_be_khong_bi_tinh_la_thieu_giay_to(): void
    {
        // Một người lớn, một trẻ em, một em bé — đúng cơ cấu mà bài này sắp khai.
        $don = $this->taoDon(guests: 3, child: 1, infant: 1);
        Sanctum::actingAs($this->khach);

        $response = $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => [
                ['name' => 'Nguyen Van An', 'type' => 'adult', 'identity_number' => '001199001234', 'is_contact' => true],
                ['name' => 'Nguyen Bao Chau', 'type' => 'child'],
                ['name' => 'Nguyen Bao Duy', 'type' => 'infant'],
            ],
        ])->assertOk();

        $canhBao = $response->json('data.warnings');

        $this->assertEmpty(
            array_filter($canhBao, fn (string $dong) => str_contains($dong, 'giấy tờ')),
            'Không được đòi giấy tờ của trẻ em và em bé.',
        );
    }

    /** Nhưng người lớn thiếu giấy tờ thì vẫn phải nhắc — khách sạn cần đúng nhóm này để khai lưu trú. */
    public function test_nguoi_lon_thieu_giay_to_thi_van_canh_bao(): void
    {
        $don = $this->taoDon(guests: 2);
        Sanctum::actingAs($this->khach);

        $response = $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => [
                ['name' => 'Nguyen Van An', 'type' => 'adult', 'identity_number' => '001199001234', 'is_contact' => true],
                ['name' => 'Tran Thi Binh', 'type' => 'adult'],
            ],
        ])->assertOk();

        $canhBao = $response->json('data.warnings');

        $this->assertNotEmpty(array_filter(
            $canhBao,
            fn (string $dong) => str_contains($dong, '1 người lớn chưa có số giấy tờ'),
        ));
    }

    /**
     * Khai được bao nhiêu lưu bấy nhiêu, và không bị nhắc vì chưa khai đủ.
     *
     * Khách đặt cho cả nhà rồi mới đi hỏi từng người số giấy tờ - lưu dở là chuyện bình thường,
     * không phải lỗi. Màn hình vẫn hiện "2 / 4 người" để họ biết còn thiếu ai; thêm một dòng cảnh
     * báo cho cùng một sự thật chỉ là nói hai lần.
     */
    public function test_khai_thieu_nguoi_van_luu_duoc_va_khong_bi_nhac(): void
    {
        $don = $this->taoDon(guests: 4);
        Sanctum::actingAs($this->khach);

        $response = $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => $this->danhSach(),
        ])->assertOk();

        $this->assertSame(2, $don->passengers()->count(), 'Khai hai người thì lưu đúng hai người.');

        $this->assertEmpty(
            array_filter($response->json('data.warnings'), fn (string $dong) => str_contains($dong, 'trên 4')),
            'Chưa khai đủ không còn là một dòng cảnh báo.',
        );
    }

    /**
     * Danh sách đoàn trả về mọi nhóm, kèm nhóm nào còn khai thiếu.
     *
     * Trước đây máy chủ lọc sẵn chỉ còn nhóm khai thiếu. Như vậy trả lời được câu "gửi danh sách
     * đi được chưa" nhưng không trả lời được câu "nhóm này gồm những ai", mà cả hai đều là việc
     * của cùng một màn hình.
     */
    public function test_danh_sach_doan_tra_ve_moi_nhom_va_chi_ro_nhom_khai_thieu(): void
    {
        $donDu = $this->taoDon(2);
        $donThieu = $this->taoDon(4);

        foreach ([$donDu, $donThieu] as $don) {
            BookingPassenger::create([
                'booking_id' => $don->id,
                'name' => 'Khach ' . $don->id,
                'type' => 'adult',
                'identity_number' => '00119900' . $don->id,
                'is_contact' => true,
            ]);
        }

        BookingPassenger::create([
            'booking_id' => $donDu->id,
            'name' => 'Khach hai',
            'type' => 'adult',
            'identity_number' => '001199777' . $donDu->id,
        ]);

        Sanctum::actingAs($this->dieuHanh);

        $response = $this->getJson("/api/admin/schedules/{$this->schedule->id}/manifest")
            ->assertOk()
            ->assertJsonPath('data.can_export_manifest', false);

        $nhom = collect($response->json('data.groups'))->keyBy('booking_id');

        // Cả hai nhóm đều có mặt, kể cả nhóm đã khai đủ.
        $this->assertTrue($nhom->has($donDu->id));
        $this->assertTrue($nhom->has($donThieu->id));

        $this->assertSame(0, $nhom[$donDu->id]['missing']);
        $this->assertSame(3, $nhom[$donThieu->id]['missing']);

        // Và mở ra thấy được nhóm đó gồm ai.
        $this->assertSame(2, count($nhom[$donDu->id]['passengers']));
        $this->assertSame('Khach ' . $donDu->id, $nhom[$donDu->id]['passengers'][0]['name']);
        $this->assertTrue($nhom[$donDu->id]['passengers'][0]['is_contact']);
    }

    /** Chưa chọn người liên hệ thì hướng dẫn viên không biết gọi ai. */
    public function test_canh_bao_khi_chua_chon_nguoi_lien_he(): void
    {
        $don = $this->taoDon(2);
        Sanctum::actingAs($this->khach);

        $response = $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => [
                ['name' => 'Nguyen Van An', 'type' => 'adult', 'identity_number' => '001199001234'],
                ['name' => 'Tran Thi Binh', 'type' => 'adult', 'identity_number' => '001199005678'],
            ],
        ])->assertOk();

        $this->assertTrue(
            collect($response->json('data.warnings'))
                ->contains(fn ($item) => str_contains($item, 'người liên hệ')),
        );
    }

    // --- Luật áp cả ở đường đặt tour ----------------------------------------------------

    /**
     * Danh sách khai lúc đặt phải chịu cùng bộ luật với danh sách sửa về sau. Hai đường ghi mà
     * hai bộ luật thì sớm muộn cũng có đơn lọt qua đường không kiểm.
     */
    public function test_dat_tour_voi_giay_to_trung_nhau_cung_bi_tu_choi(): void
    {
        $this->postJson('/api/bookings', [
            'tour_id' => $this->schedule->tour_id,
            'tour_schedule_id' => $this->schedule->id,
            'customer_name' => 'Nguyen Van An',
            'customer_email' => 'khach@example.com',
            'customer_phone' => '0901234567',
            'adult_count' => 2,
            'accept_terms' => true,
            'passengers' => [
                ['name' => 'Nguyen Van An', 'type' => 'adult', 'identity_number' => '001199001234'],
                ['name' => 'Tran Thi Binh', 'type' => 'adult', 'identity_number' => '001199001234'],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, Booking::query()->count());
    }
}
