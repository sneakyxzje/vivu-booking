<?php

namespace Tests\Feature;

use App\Enums\HandoverRequestStatus;
use App\Enums\ScheduleAuditAction;
use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Models\GuideHandover;
use App\Models\GuideHandoverRequest;
use App\Models\ScheduleAuditLog;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Hướng dẫn viên xin được bàn giao, điều hành chọn người thay rồi duyệt.
 *
 * Hai điều bộ test này giữ:
 *
 *   1. **Duyệt đi qua đúng đường bàn giao chung**, không tự thực hiện. Hai đường ghi cho cùng một
 *      việc là khuôn của phần lớn lỗi ở dự án này.
 *   2. **Chờ duyệt thì người xin vẫn giữ quyền phụ trách.** Đó là điểm an toàn của việc phải chờ:
 *      không có khoảnh khắc nào đoàn không có ai chịu trách nhiệm trên hệ thống.
 */
class GuideHandoverRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private User $nguoiDan;
    private User $nguoiThay;
    private User $nguoiOLai;
    private Tour $tour;
    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dieuHanh = $this->taoNguoi('admin');
        $this->nguoiDan = $this->taoNguoi('guide');
        $this->nguoiThay = $this->taoNguoi('guide');

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 3,
            'adult_price' => 2_000_000,
        ]);

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

        // Người thứ hai ở lại với đoàn: đoàn đang trên đường nên phải có người này thì lúc duyệt
        // mới bàn giao được. Gửi yêu cầu thì không cần - người đang ốm vẫn phải xin được.
        $this->nguoiOLai = $this->taoNguoi('guide');

        $this->chuyen->guides()->sync([$this->nguoiDan->id, $this->nguoiOLai->id]);
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

    private function payload(array $ghiDe = []): array
    {
        return array_merge([
            'reason' => 'Toi bi sot cao tu sang, khong dan tiep duoc.',
            'group_state' => 'Doan dang o Bai Chay, da diem danh xong chang mot. Khach Nguyen Van A '
                . 'bi say xe, can de y. Chieu con lich di thuyen.',
        ], $ghiDe);
    }

    private function guiYeuCau(): GuideHandoverRequest
    {
        Sanctum::actingAs($this->nguoiDan);

        $this->postJson(
            '/api/guide/schedules/' . $this->chuyen->id . '/handover-request',
            $this->payload(),
        )->assertOk();

        return GuideHandoverRequest::query()->latest('id')->first();
    }

    // --- Hướng dẫn viên gửi ---------------------------------------------------------------

    public function test_huong_dan_vien_gui_duoc_yeu_cau_va_van_giu_quyen_cho_toi_khi_duyet(): void
    {
        $yeuCau = $this->guiYeuCau();

        $this->assertSame(HandoverRequestStatus::Pending, $yeuCau->status);
        $this->assertSame($this->nguoiDan->id, $yeuCau->requested_by);

        $this->assertTrue(
            $this->chuyen->fresh()->hasGuide($this->nguoiDan->id),
            'Chờ duyệt thì người xin vẫn phụ trách, đoàn không lúc nào mất người.',
        );

        // Và vẫn ghi được: chưa duyệt thì chưa mất quyền.
        $this->getJson('/api/guide/schedules/' . $this->chuyen->id . '/attendance')->assertOk();
    }

    public function test_khong_xin_duoc_cho_chuyen_minh_khong_dan(): void
    {
        $nguoiLa = $this->taoNguoi('guide');

        Sanctum::actingAs($nguoiLa);

        $this->postJson(
            '/api/guide/schedules/' . $this->chuyen->id . '/handover-request',
            $this->payload(),
        )->assertStatus(422);
    }

    public function test_thieu_tinh_trang_doan_thi_khong_gui_duoc(): void
    {
        Sanctum::actingAs($this->nguoiDan);

        $this->postJson(
            '/api/guide/schedules/' . $this->chuyen->id . '/handover-request',
            $this->payload(['group_state' => 'Om roi.']),
        )->assertStatus(422);
    }

    public function test_khong_gui_hai_yeu_cau_cung_luc_cho_mot_chuyen(): void
    {
        $this->guiYeuCau();

        Sanctum::actingAs($this->nguoiDan);

        $this->postJson(
            '/api/guide/schedules/' . $this->chuyen->id . '/handover-request',
            $this->payload(),
        )->assertStatus(422);
    }

    /*
     * Không còn bài "rút lại phiếu": hướng dẫn viên đỡ rồi thì gọi cho điều hành, điều hành đóng
     * phiếu kèm ghi chú. Một thao tác thay vì một trạng thái riêng.
     */

    // --- Điều hành xử lý phiếu ------------------------------------------------------------

    /**
     * Bài quan trọng nhất: xử lý phiếu phải sinh ra đúng một biên bản bàn giao thật.
     *
     * Nếu ai đó về sau cài lại thành "tự đổi người cho nhanh" thì bài này đỏ, vì sẽ không có biên
     * bản và không có dòng nhật ký chuyến nào.
     */
    public function test_duyet_di_qua_dung_duong_ban_giao_chung(): void
    {
        $yeuCau = $this->guiYeuCau();

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/handover-requests/' . $yeuCau->id . '/resolve', [
            'to_guide_id' => $this->nguoiThay->id,
        ])->assertOk();

        $bienBan = GuideHandover::query()->latest('id')->first();

        $this->assertNotNull($bienBan, 'Duyệt phải sinh biên bản bàn giao thật.');
        $this->assertSame($this->nguoiDan->id, $bienBan->from_guide_id);
        $this->assertSame($this->nguoiThay->id, $bienBan->to_guide_id);

        // Chữ của người đang đứng cùng đoàn phải đi thẳng vào biên bản, không qua tay ai gõ lại.
        $this->assertStringContainsString('Bai Chay', $bienBan->handover_note);
        $this->assertStringContainsString('sot cao', $bienBan->reason);

        $this->assertSame(
            $bienBan->id,
            $yeuCau->fresh()->guide_handover_id,
            'Yêu cầu phải trỏ vào biên bản sinh ra từ nó.',
        );

        $this->assertSame(
            1,
            ScheduleAuditLog::query()
                ->where('tour_schedule_id', $this->chuyen->id)
                ->where('action', ScheduleAuditAction::GuideHandover->value)
                ->count(),
        );

        $daTai = $this->chuyen->fresh();
        $this->assertFalse($daTai->hasGuide($this->nguoiDan->id));
        $this->assertTrue($daTai->hasGuide($this->nguoiThay->id));
    }

    /**
     * Luật "không bỏ rơi đoàn" áp lúc duyệt, không áp lúc gửi yêu cầu.
     *
     * Người đang ốm vẫn phải xin được — chặn từ đầu là bịt miệng người đang cần giúp. Điều hành
     * nhận yêu cầu, thấy chuyến chỉ có một người thì bổ sung người trước rồi mới duyệt.
     */
    public function test_van_xin_duoc_du_chuyen_chi_co_mot_nguoi_nhung_chua_duyet_duoc(): void
    {
        $this->chuyen->guides()->detach($this->nguoiOLai->id);

        $yeuCau = $this->guiYeuCau();

        $this->assertSame(HandoverRequestStatus::Pending, $yeuCau->status);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/handover-requests/' . $yeuCau->id . '/resolve', [
            'to_guide_id' => $this->nguoiThay->id,
        ])->assertStatus(422);

        $this->assertSame(HandoverRequestStatus::Pending, $yeuCau->fresh()->status);

        // Bổ sung người ở lại rồi thì duyệt được.
        $this->chuyen->guides()->attach($this->nguoiOLai->id);

        $this->putJson('/api/admin/handover-requests/' . $yeuCau->id . '/resolve', [
            'to_guide_id' => $this->nguoiThay->id,
        ])->assertOk();

        $this->assertSame(HandoverRequestStatus::Closed, $yeuCau->fresh()->status);
    }

    public function test_duyet_ma_khong_chon_nguoi_thay_thi_bi_tu_choi(): void
    {
        $yeuCau = $this->guiYeuCau();

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/handover-requests/' . $yeuCau->id . '/resolve', [])
            ->assertStatus(422);

        $this->assertSame(HandoverRequestStatus::Pending, $yeuCau->fresh()->status);
    }

    /** Người thay vướng lịch thì cả lần duyệt bị từ chối, yêu cầu vẫn nằm chờ. */
    public function test_nguoi_thay_trung_lich_thi_khong_duyet_duoc(): void
    {
        $yeuCau = $this->guiYeuCau();

        $chuyenKhac = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now(),
            'end_date' => now()->addDays(2),
            'booking_deadline' => now()->subDays(3),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ]);

        $chuyenKhac->guides()->sync([$this->nguoiThay->id]);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/handover-requests/' . $yeuCau->id . '/resolve', [
            'to_guide_id' => $this->nguoiThay->id,
        ])->assertStatus(422);

        $this->assertSame(HandoverRequestStatus::Pending, $yeuCau->fresh()->status);
        $this->assertTrue($this->chuyen->fresh()->hasGuide($this->nguoiDan->id));
        $this->assertSame(0, GuideHandover::query()->count());
    }

    /** Từ chối thì người xin giữ nguyên quyền, và phải có lý do để họ đọc. */
    public function test_tu_choi_phai_ghi_ly_do_va_nguoi_xin_giu_nguyen_quyen(): void
    {
        $yeuCau = $this->guiYeuCau();

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/handover-requests/' . $yeuCau->id . '/close', [])
            ->assertStatus(422);

        $this->putJson('/api/admin/handover-requests/' . $yeuCau->id . '/close', [
            'review_note' => 'Khong tim duoc nguoi thay, cong ty se cu nguoi ho tro toi noi.',
        ])->assertOk();

        $this->assertSame(HandoverRequestStatus::Closed, $yeuCau->fresh()->status);
        $this->assertTrue($this->chuyen->fresh()->hasGuide($this->nguoiDan->id));
    }

    public function test_yeu_cau_da_xu_ly_thi_khong_duyet_lai(): void
    {
        $yeuCau = $this->guiYeuCau();

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/handover-requests/' . $yeuCau->id . '/resolve', [
            'to_guide_id' => $this->nguoiThay->id,
        ])->assertOk();

        $this->putJson('/api/admin/handover-requests/' . $yeuCau->id . '/resolve', [
            'to_guide_id' => $this->nguoiThay->id,
        ])->assertStatus(422);
    }

    public function test_danh_sach_cho_duyet_hien_dung_chu_cua_huong_dan_vien(): void
    {
        $this->guiYeuCau();

        Sanctum::actingAs($this->dieuHanh);

        $ds = $this->getJson('/api/admin/handover-requests')->assertOk()->json('data');

        $this->assertCount(1, $ds);
        $this->assertSame($this->nguoiDan->name, $ds[0]['requester_name']);
        $this->assertStringContainsString('Bai Chay', $ds[0]['group_state']);
    }
}
