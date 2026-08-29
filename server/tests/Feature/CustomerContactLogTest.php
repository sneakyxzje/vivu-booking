<?php

namespace Tests\Feature;

use App\Enums\ContactChannel;
use App\Enums\ContactOutcome;
use App\Enums\ContactPurpose;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\CustomerContactLog;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Nhật ký liên hệ khách.
 *
 * **Điều bộ test này giữ: nhật ký ghi được cả những lần không thành, và không sửa được.**
 *
 * Chỉ cho ghi lúc khách đồng ý thì nó thành bộ sưu tập tin vui - không ai tra ngược được rằng công
 * ty đã gọi bốn lần mà không ai bắt máy, mà đó chính là thứ cần đến khi có tranh cãi. Còn cho sửa
 * thì nó thành thứ người ta muốn nó là, và lúc ấy nó không chứng minh được gì nữa.
 */
class CustomerContactLogTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private Booking $don;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dieuHanh = $this->taoNguoi('admin');

        $tour = Tour::factory()->create(['status' => 'active', 'number_of_days' => 2]);

        $chuyen = TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(30),
            'end_date' => now()->addDays(31),
            'booking_deadline' => now()->addDays(27),
            'max_people' => 10,
            'min_people' => 2,
            'booked_people' => 2,
        ]);

        $this->don = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Khach Lien He',
            'customer_email' => 'lienhe-' . Str::random(5) . '@example.com',
            'departure_date' => $chuyen->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 4_000_000,
            'status' => 'confirmed',
            'paid_at' => now()->subDay(),
            'confirmed_at' => now()->subDay(),
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

    /** @param  array<string, mixed>  $ghiDe */
    private function ghiNhan(array $ghiDe = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/admin/bookings/{$this->don->id}/contact-logs", array_merge([
            'channel' => ContactChannel::Phone->value,
            'purpose' => ContactPurpose::Transfer->value,
            'outcome' => ContactOutcome::Agreed->value,
            'note' => 'Da goi, khach dong y doi sang chuyen ngay 20.',
        ], $ghiDe));
    }

    // --- Ghi ------------------------------------------------------------------------------

    public function test_ghi_duoc_mot_cuoc_lien_he(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->ghiNhan()->assertOk();

        $tb = CustomerContactLog::query()->firstOrFail();

        $this->assertSame($this->don->id, (int) $tb->booking_id);
        $this->assertSame(ContactOutcome::Agreed, $tb->outcome);
        $this->assertSame($this->dieuHanh->id, (int) $tb->contacted_by);
        $this->assertNotNull($tb->contacted_at, 'Không ghi thời điểm thì bản ghi không tra ngược được.');
    }

    /**
     * Ghi được cả hai kết quả xấu.
     *
     * Đây là nửa quan trọng hơn của nhật ký: bốn lần gọi không ai bắt máy là bằng chứng công ty đã
     * cố liên hệ. Không ghi được thì chỉ còn lời khai.
     */
    public function test_ghi_duoc_ca_khi_khach_tu_choi_hoac_khong_lien_lac_duoc(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        foreach ([ContactOutcome::Refused, ContactOutcome::Unreachable] as $ketQua) {
            $this->ghiNhan([
                'outcome' => $ketQua->value,
                'note' => 'Da goi lan nua, ket qua: ' . $ketQua->label(),
            ])->assertOk();
        }

        $this->assertSame(2, CustomerContactLog::query()->count());
    }

    /** Bản ghi không có nội dung chỉ chứng minh được là có người bấm nút. */
    public function test_phai_ghi_noi_dung_trao_doi(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->ghiNhan(['note' => 'ok'])->assertStatus(422)->assertJsonValidationErrors('note');
        $this->ghiNhan(['note' => ''])->assertStatus(422)->assertJsonValidationErrors('note');
    }

    public function test_khong_ghi_duoc_cho_don_khong_ton_tai(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/bookings/999999/contact-logs', [
            'channel' => ContactChannel::Phone->value,
            'purpose' => ContactPurpose::Transfer->value,
            'outcome' => ContactOutcome::Agreed->value,
            'note' => 'Goi cho mot don khong co that.',
        ])->assertStatus(404);
    }

    // --- Không sửa, không xóa ---------------------------------------------------------------

    /**
     * Không có đường sửa hay xóa, và ràng buộc nằm ở tầng tuyến đường.
     *
     * Chặn bằng một câu kiểm tra trong controller thì vẫn còn cửa cho người sau mở lại mà không
     * biết vì sao nó bị đóng. Không có tuyến thì không có gì để mở.
     */
    public function test_khong_sua_va_khong_xoa_duoc_ban_ghi(): void
    {
        Sanctum::actingAs($this->dieuHanh);
        $this->ghiNhan()->assertOk();

        $id = CustomerContactLog::query()->value('id');

        $this->putJson("/api/admin/bookings/{$this->don->id}/contact-logs/{$id}", [
            'note' => 'Sua lai cho dep.',
        ])->assertStatus(404);

        $this->deleteJson("/api/admin/bookings/{$this->don->id}/contact-logs/{$id}")
            ->assertStatus(404);

        $this->assertSame(1, CustomerContactLog::query()->count());
    }

    // --- Đọc ------------------------------------------------------------------------------

    /** Danh sách nói sẵn bản nào dùng làm căn cứ chuyển chuyến được, để màn hình khỏi phải đoán. */
    public function test_danh_sach_danh_dau_ban_nao_dung_lam_can_cu_duoc(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->ghiNhan()->assertOk();
        $this->ghiNhan([
            'outcome' => ContactOutcome::Refused->value,
            'note' => 'Khach khong dong y doi ngay.',
        ])->assertOk();

        $ds = $this->getJson("/api/admin/bookings/{$this->don->id}/contact-logs")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $ds);

        $dongY = collect($ds)->firstWhere('outcome', ContactOutcome::Agreed->value);
        $tuChoi = collect($ds)->firstWhere('outcome', ContactOutcome::Refused->value);

        $this->assertTrue($dongY['dung_lam_can_cu_duoc']);
        $this->assertFalse($tuChoi['dung_lam_can_cu_duoc']);
    }

    public function test_khach_khong_goi_duoc_api_nhat_ky_lien_he(): void
    {
        Sanctum::actingAs($this->taoNguoi('customer'));

        $this->getJson("/api/admin/bookings/{$this->don->id}/contact-logs")->assertForbidden();
        $this->ghiNhan()->assertForbidden();
    }
}
