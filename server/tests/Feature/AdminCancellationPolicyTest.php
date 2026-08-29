<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\CancellationPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * B05 - Chính sách hủy. **Một bảng phí duy nhất cho toàn hệ thống.**
 *
 * Trước đây đây là màn danh sách: tạo nhiều chính sách, mỗi tour chọn một cái. Bỏ đi vì nó sinh
 * ra câu hỏi "đơn này áp bảng nào" ở mọi màn hình chạm tới tiền.
 *
 * Điều bộ test này giữ, và là điều duy nhất còn tinh tế sau khi rút gọn: **sửa bảng phí không
 * hồi tố lên đơn đã bán.** Đơn chép bảng phí vào chính nó lúc đặt, nên sửa một con số hôm nay
 * không đổi điều khoản khách đã đồng ý hôm qua.
 */
class AdminCancellationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function dangNhapAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        return $admin;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bacPhiHopLe(): array
    {
        return [
            ['min_days_before' => 15, 'max_days_before' => null, 'refund_percent' => 90],
            ['min_days_before' => 2, 'max_days_before' => 15, 'refund_percent' => 50],
            ['min_days_before' => 0, 'max_days_before' => 2, 'refund_percent' => 0],
        ];
    }

    // --- Đọc ------------------------------------------------------------------------------

    /**
     * Cơ sở dữ liệu trống thì tự dựng bảng mặc định, không trả về rỗng.
     *
     * Màn chính sách mở ra trống trơn thì người dùng tưởng hệ thống không áp mức nào - trong khi
     * lớp dịch vụ vẫn có bảng phí viết sẵn trong mã và vẫn đang tính theo nó.
     */
    public function test_chua_co_gi_thi_tu_dung_bang_mac_dinh(): void
    {
        $this->dangNhapAdmin();

        $this->assertSame(0, CancellationPolicy::query()->count());

        $this->getJson('/api/admin/cancellation-policies')
            ->assertOk()
            ->assertJsonCount(count(CancellationPolicyService::DEFAULT_RULES), 'data.rules');

        $this->assertSame(1, CancellationPolicy::query()->count());
    }

    public function test_doc_ve_dung_bang_phi_dang_ap_dung(): void
    {
        $this->dangNhapAdmin();

        $policy = CancellationPolicy::query()->create([
            'name' => 'Bảng phí đang chạy',
            'effective_from' => now()->subDay(),
        ]);

        foreach ($this->bacPhiHopLe() as $rule) {
            $policy->rules()->create($rule);
        }

        $this->getJson('/api/admin/cancellation-policies')
            ->assertOk()
            ->assertJsonPath('data.id', $policy->id)
            ->assertJsonPath('data.name', 'Bảng phí đang chạy')
            ->assertJsonCount(3, 'data.rules');
    }

    // --- Ghi ------------------------------------------------------------------------------

    public function test_sua_thay_toan_bo_cac_bac(): void
    {
        $this->dangNhapAdmin();

        $this->getJson('/api/admin/cancellation-policies')->assertOk();

        $this->putJson('/api/admin/cancellation-policies', [
            'effective_from' => now()->format('Y-m-d H:i:s'), 'name' => 'Bảng phí mới',
            'description' => 'Rút còn hai bậc cho gọn.',
            'rules' => [
                ['min_days_before' => 7, 'max_days_before' => null, 'refund_percent' => 80],
                ['min_days_before' => 0, 'max_days_before' => 7, 'refund_percent' => 20],
            ],
        ])->assertOk();

        $policy = CancellationPolicy::dangApDung();

        $this->assertSame('Bảng phí mới', $policy->name);
        $this->assertCount(2, $policy->rules, 'Bảng đang áp dụng phải là bảng vừa nhập.');

        // Bản cũ ở lại làm phiên bản lịch sử cho đơn đã trỏ vào, chỉ thôi là bản mới nhất.
        $this->assertSame(2, CancellationPolicy::query()->count());
    }

    /** Không có bậc từ 0 ngày thì hủy sát ngày đi không rơi vào bậc nào và lặng lẽ hoàn 0. */
    public function test_phai_co_bac_bat_dau_tu_khong_ngay(): void
    {
        $this->dangNhapAdmin();

        $this->putJson('/api/admin/cancellation-policies', [
            'effective_from' => now()->format('Y-m-d H:i:s'), 'name' => 'Thiếu bậc cuối',
            'rules' => [
                ['min_days_before' => 2, 'max_days_before' => null, 'refund_percent' => 50],
            ],
        ])->assertStatus(422);
    }

    public function test_moc_tren_phai_lon_hon_moc_duoi(): void
    {
        $this->dangNhapAdmin();

        $this->putJson('/api/admin/cancellation-policies', [
            'effective_from' => now()->format('Y-m-d H:i:s'), 'name' => 'Bậc ngược',
            'rules' => [
                ['min_days_before' => 0, 'max_days_before' => 5, 'refund_percent' => 50],
                ['min_days_before' => 10, 'max_days_before' => 5, 'refund_percent' => 80],
            ],
        ])->assertStatus(422);
    }

    public function test_muc_hoan_khong_vuot_qua_mot_tram(): void
    {
        $this->dangNhapAdmin();

        $this->putJson('/api/admin/cancellation-policies', [
            'effective_from' => now()->format('Y-m-d H:i:s'), 'name' => 'Hoàn quá tay',
            'rules' => [
                ['min_days_before' => 0, 'max_days_before' => null, 'refund_percent' => 150],
            ],
        ])->assertStatus(422);
    }

    // --- Mốc hiệu lực ---------------------------------------------------------------------

    /**
     * Bảng phí hẹn cho tương lai nằm im tới đúng giờ của nó.
     *
     * Đây là cách một công ty thật đổi điều khoản: công bố trước, áp sau. Nếu bản hẹn có hiệu lực
     * ngay lúc bấm lưu thì việc công bố trước thành ra lừa khách - họ đọc một bảng phí rồi đặt
     * theo một bảng khác.
     */
    public function test_ban_hen_cho_tuong_lai_chua_ap_dung_ngay(): void
    {
        $this->dangNhapAdmin();

        $this->putJson('/api/admin/cancellation-policies', [
            'effective_from' => now()->format('Y-m-d H:i:s'),
            'name' => 'Bảng đang chạy',
            'rules' => [
                ['min_days_before' => 0, 'max_days_before' => null, 'refund_percent' => 90],
            ],
        ])->assertOk();

        $this->putJson('/api/admin/cancellation-policies', [
            'effective_from' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'name' => 'Bảng siết lại từ tháng sau',
            'rules' => [
                ['min_days_before' => 0, 'max_days_before' => null, 'refund_percent' => 10],
            ],
        ])->assertOk();

        $this->assertSame(
            'Bảng đang chạy',
            CancellationPolicy::dangApDung()->name,
            'Chưa tới ngày thì bản cũ vẫn là bản áp dụng.',
        );

        $this->assertSame(
            'Bảng siết lại từ tháng sau',
            CancellationPolicy::dangApDung(now()->addDays(31))->name,
            'Tới ngày thì bản mới thay chỗ, không cần ai bật cờ.',
        );
    }

    /**
     * Màn hình mở ra phải thấy bản mình vừa hẹn, không phải bản đang chạy.
     *
     * Người vừa đặt lịch một bảng phí cho tháng sau mà mở lại thấy bảng cũ thì sẽ tưởng thao tác
     * của mình không ăn, và bấm lưu thêm lần nữa.
     */
    public function test_doc_ve_ban_moi_nhat_ke_ca_khi_chua_toi_gio(): void
    {
        $this->dangNhapAdmin();

        $this->putJson('/api/admin/cancellation-policies', [
            'effective_from' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'name' => 'Bảng hẹn cho tháng sau',
            'rules' => [
                ['min_days_before' => 0, 'max_days_before' => null, 'refund_percent' => 10],
            ],
        ])->assertOk();

        $this->getJson('/api/admin/cancellation-policies')
            ->assertOk()
            ->assertJsonPath('data.name', 'Bảng hẹn cho tháng sau');
    }

    /** Đặt mốc vào quá khứ bị chặn: nó gợi ý một sự hồi tố mà hệ thống cố ý không làm. */
    public function test_khong_dat_duoc_moc_ap_dung_vao_qua_khu(): void
    {
        $this->dangNhapAdmin();

        $this->putJson('/api/admin/cancellation-policies', [
            'effective_from' => now()->subDays(3)->format('Y-m-d H:i:s'),
            'name' => 'Bảng phí ký lùi',
            'rules' => [
                ['min_days_before' => 0, 'max_days_before' => null, 'refund_percent' => 50],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, CancellationPolicy::query()->count());
    }

    /** Mốc hiệu lực là bắt buộc: không có nó thì không trả lời được câu "từ bao giờ". */
    public function test_thieu_moc_hieu_luc_thi_khong_luu_duoc(): void
    {
        $this->dangNhapAdmin();

        $this->putJson('/api/admin/cancellation-policies', [
            'name' => 'Không ghi từ bao giờ',
            'rules' => $this->bacPhiHopLe(),
        ])->assertStatus(422)->assertJsonValidationErrors('effective_from');
    }

    // --- Chỉ có một -----------------------------------------------------------------------

    /**
     * Không có đường nào tạo thêm hay xóa bảng phí.
     *
     * Ràng buộc nằm ở tầng tuyến đường: hai động từ ấy không tồn tại nữa. Chặn bằng một câu kiểm
     * tra trong controller thì vẫn còn cửa cho người sau mở lại mà không biết vì sao nó bị đóng.
     */
    public function test_khong_tao_them_va_khong_xoa_duoc_bang_phi(): void
    {
        $this->dangNhapAdmin();

        // POST lên đúng đường dẫn: có tuyến nhưng không có động từ này -> 405.
        $this->postJson('/api/admin/cancellation-policies', [
            'effective_from' => now()->format('Y-m-d H:i:s'), 'name' => 'Bảng thứ hai',
            'rules' => $this->bacPhiHopLe(),
        ])->assertStatus(405);

        $this->getJson('/api/admin/cancellation-policies')->assertOk();
        $id = CancellationPolicy::query()->value('id');

        // DELETE có id: cả tuyến đường ấy cũng không còn tồn tại -> 404.
        $this->deleteJson('/api/admin/cancellation-policies/' . $id)->assertStatus(404);

        $this->assertSame(1, CancellationPolicy::query()->count());
    }

    public function test_khach_khong_goi_duoc_api_quan_tri(): void
    {
        $khach = User::create([
            'name' => 'Khach',
            'email' => 'khach-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($khach);

        $this->getJson('/api/admin/cancellation-policies')->assertForbidden();
        $this->putJson('/api/admin/cancellation-policies', [
            'effective_from' => now()->format('Y-m-d H:i:s'), 'name' => 'Thử sửa',
            'rules' => $this->bacPhiHopLe(),
        ])->assertForbidden();
    }

    // --- Không hồi tố ---------------------------------------------------------------------

    /**
     * Bài quan trọng nhất còn lại.
     *
     * Khách đặt hôm qua theo bảng hoàn 90%. Hôm nay công ty sửa bảng xuống 10%. Đơn hôm qua phải
     * vẫn được 90% - đó là điều khoản họ đã đồng ý, và sửa một con số trên màn quản trị không
     * phải là cách thương lượng lại hợp đồng đã ký.
     */
    public function test_sua_bang_phi_khong_hoi_to_len_don_da_dat(): void
    {
        $this->dangNhapAdmin();

        // Bảng phí lúc khách đặt: hủy sớm hoàn 90%.
        $this->putJson('/api/admin/cancellation-policies', [
            'effective_from' => now()->format('Y-m-d H:i:s'), 'name' => 'Bảng phí lúc khách đặt',
            'rules' => [
                ['min_days_before' => 10, 'max_days_before' => null, 'refund_percent' => 90],
                ['min_days_before' => 0, 'max_days_before' => 10, 'refund_percent' => 0],
            ],
        ])->assertOk();

        $don = $this->taoDonDaChepChinhSach();

        // Công ty siết lại: cùng khoảng thời gian ấy giờ chỉ hoàn 10%.
        $this->putJson('/api/admin/cancellation-policies', [
            'effective_from' => now()->format('Y-m-d H:i:s'), 'name' => 'Bảng phí siết lại',
            'rules' => [
                ['min_days_before' => 10, 'max_days_before' => null, 'refund_percent' => 10],
                ['min_days_before' => 0, 'max_days_before' => 10, 'refund_percent' => 0],
            ],
        ])->assertOk();

        $bao = app(CancellationPolicyService::class)->quote($don->fresh(), $don->schedule);

        $this->assertSame(
            90,
            $bao['refund_percent'],
            'Đơn đã đặt phải giữ đúng bảng phí lúc khách đồng ý.',
        );
    }

    /** Đơn đặt SAU khi sửa thì theo bảng mới - đó là nửa còn lại của cùng một quy tắc. */
    public function test_don_dat_sau_khi_sua_thi_theo_bang_moi(): void
    {
        $this->dangNhapAdmin();

        $this->putJson('/api/admin/cancellation-policies', [
            'effective_from' => now()->format('Y-m-d H:i:s'), 'name' => 'Bảng phí siết lại',
            'rules' => [
                ['min_days_before' => 10, 'max_days_before' => null, 'refund_percent' => 10],
                ['min_days_before' => 0, 'max_days_before' => 10, 'refund_percent' => 0],
            ],
        ])->assertOk();

        $don = $this->taoDonDaChepChinhSach();

        $bao = app(CancellationPolicyService::class)->quote($don->fresh(), $don->schedule);

        $this->assertSame(10, $bao['refund_percent']);
    }

    /**
     * Dựng một đơn đã chép bảng phí đang hiện hành, đúng cách BookingController làm lúc đặt.
     */
    private function taoDonDaChepChinhSach(): Booking
    {
        $tour = Tour::factory()->create(['status' => 'active', 'number_of_days' => 2]);

        $chuyen = TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(20),
            'end_date' => now()->addDays(21),
            'booking_deadline' => now()->addDays(17),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 2,
        ]);

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Khach ' . Str::random(4),
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $chuyen->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 4_000_000,
            'status' => 'confirmed',
            'paid_at' => now(),
            'confirmed_at' => now(),
            'cancellation_policy_id' => CancellationPolicy::dangApDung()?->id,
        ]);
    }
}
