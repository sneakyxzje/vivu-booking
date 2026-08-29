<?php

namespace Tests\Feature;

use App\Enums\ScheduleAuditAction;
use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Models\GuideHandover;
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
 * Đổi hướng dẫn viên giữa chừng chuyến, kèm biên bản bàn giao.
 *
 * Đổi người vốn đã làm được bằng màn phân công; thứ thiếu là **vết**. Bộ test này giữ hai điều:
 * bàn giao luôn để lại biên bản có tình trạng đoàn, và người cũ mất quyền ghi ngay lập tức.
 */
class GuideHandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private User $nguoiCu;
    private User $nguoiMoi;
    /** Người thứ hai ở lại với đoàn. Đoàn đang đi thì phải có người này mới bàn giao được. */
    private User $nguoiOLai;
    private Tour $tour;
    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dieuHanh = $this->taoNguoi('admin');
        $this->nguoiCu = $this->taoNguoi('guide');
        $this->nguoiMoi = $this->taoNguoi('guide');
        $this->nguoiOLai = $this->taoNguoi('guide');

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 3,
            'adult_price' => 2_000_000,
        ]);

        // Đoàn đang đi: đây mới là tình huống "thay giữa chừng".
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

        // Hai người: đoàn đang trên đường nên phải còn người ở lại thì mới bàn giao được.
        $this->chuyen->guides()->sync([$this->nguoiCu->id, $this->nguoiOLai->id]);
    }

    private function taoNguoi(string $role, string $status = 'active'): User
    {
        return User::create([
            'name' => ucfirst($role) . ' ' . Str::random(4),
            'email' => Str::random(8) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function payload(array $ghiDe = []): array
    {
        return array_merge([
            'from_guide_id' => $this->nguoiCu->id,
            'to_guide_id' => $this->nguoiMoi->id,
            'reason' => 'Huong dan vien cu bi sot cao, phai ve som.',
            'handover_note' => 'Doan dang o Bai Chay, da diem danh xong chang mot, con hai khach '
                . 'chua an trua. Khach Nguyen Van A bi say xe can de y.',
        ], $ghiDe);
    }

    // --- Luồng chính ----------------------------------------------------------------------

    public function test_ban_giao_thi_doi_nguoi_va_de_lai_bien_ban(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/handover', $this->payload())
            ->assertOk();

        $daTai = $this->chuyen->fresh();

        $this->assertFalse($daTai->hasGuide($this->nguoiCu->id), 'Người cũ phải rời danh sách phụ trách.');
        $this->assertTrue($daTai->hasGuide($this->nguoiMoi->id));

        $bienBan = GuideHandover::query()->latest('id')->first();

        $this->assertNotNull($bienBan);
        $this->assertSame($this->nguoiCu->id, $bienBan->from_guide_id);
        $this->assertSame($this->nguoiMoi->id, $bienBan->to_guide_id);
        $this->assertStringContainsString('Bai Chay', $bienBan->handover_note);
        $this->assertSame($this->dieuHanh->id, $bienBan->created_by);
    }

    /**
     * Người cũ mất quyền ghi ngay, không có khoảng lửng lơ nào cả hai cùng ghi được.
     *
     * Kiểm qua đường điểm danh vì đó là quyền ghi thật sự đáng lo: hai người cùng tick một đoàn
     * thì dữ liệu đối chiếu sau chuyến không còn tin được.
     */
    public function test_nguoi_cu_mat_quyen_ghi_ngay_sau_khi_ban_giao(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/handover', $this->payload())
            ->assertOk();

        Sanctum::actingAs($this->nguoiCu);

        $this->getJson('/api/guide/schedules/' . $this->chuyen->id . '/attendance')
            ->assertStatus(404);

        Sanctum::actingAs($this->nguoiMoi);

        $this->getJson('/api/guide/schedules/' . $this->chuyen->id . '/attendance')
            ->assertOk();
    }

    public function test_bien_ban_duoc_ghi_vao_nhat_ky_chuyen(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/handover', $this->payload())
            ->assertOk();

        $log = ScheduleAuditLog::query()
            ->where('tour_schedule_id', $this->chuyen->id)
            ->where('action', ScheduleAuditAction::GuideHandover->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($this->nguoiCu->id, $log->old_values['guide_id']);
        $this->assertSame($this->nguoiMoi->id, $log->new_values['guide_id']);
        $this->assertStringContainsString('sot cao', $log->reason);
    }

    // --- Bắt buộc có tình trạng đoàn -------------------------------------------------------

    /** Bàn giao mà không nói tình trạng đoàn thì người mới vẫn phải mò lại từ đầu. */
    public function test_thieu_tinh_trang_doan_thi_khong_ban_giao_duoc(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->postJson(
            '/api/admin/schedules/' . $this->chuyen->id . '/handover',
            $this->payload(['handover_note' => 'Ban giao.']),
        )->assertStatus(422);

        $this->assertTrue(
            $this->chuyen->fresh()->hasGuide($this->nguoiCu->id),
            'Bị từ chối thì người cũ phải giữ nguyên quyền.',
        );
    }

    public function test_thieu_ly_do_thi_khong_ban_giao_duoc(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->postJson(
            '/api/admin/schedules/' . $this->chuyen->id . '/handover',
            $this->payload(['reason' => 'om']),
        )->assertStatus(422);
    }

    // --- Luật chặn ------------------------------------------------------------------------

    public function test_nguoi_giao_phai_dang_phu_trach_chuyen(): void
    {
        $nguoiLa = $this->taoNguoi('guide');

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson(
            '/api/admin/schedules/' . $this->chuyen->id . '/handover',
            $this->payload(['from_guide_id' => $nguoiLa->id]),
        )->assertStatus(422);
    }

    public function test_nguoi_nhan_dang_ban_chuyen_khac_thi_bi_tu_choi(): void
    {
        // Chuyến khác chồng ngày, tour 3 ngày nên cách một ngày là đã chạm nhau.
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

        $chuyenKhac->guides()->sync([$this->nguoiMoi->id]);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/handover', $this->payload())
            ->assertStatus(422);

        $this->assertTrue($this->chuyen->fresh()->hasGuide($this->nguoiCu->id));
    }

    // --- Không bỏ rơi đoàn giữa đường ------------------------------------------------------

    /**
     * Đoàn đang đi mà chỉ có một hướng dẫn viên thì không gỡ người đó ra được.
     *
     * Trên giấy tờ là "đã bàn giao", ngoài thực địa là ba mươi khách đứng ở bến tàu không biết
     * hỏi ai, suốt quãng thời gian người mới di chuyển tới.
     */
    public function test_doan_dang_di_mot_nguoi_ma_nguoi_thay_dang_o_nha_thi_khong_duoc(): void
    {
        // Bỏ người thứ hai đi: giờ chuyến chỉ còn đúng một hướng dẫn viên.
        $this->chuyen->guides()->detach($this->nguoiOLai->id);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/handover', $this->payload())
            ->assertStatus(422);

        $this->assertTrue(
            $this->chuyen->fresh()->hasGuide($this->nguoiCu->id),
            'Bị chặn thì người cũ giữ nguyên quyền, đoàn không lúc nào mất người.',
        );
    }

    /*
     * Đã gỡ hai bài về "nhờ hướng dẫn viên đoàn khác trông hộ".
     *
     * Lối thoát ấy cho một người giữ hai đoàn cùng lúc, tức phá chính luật trùng lịch hệ thống
     * chặn ở mọi chỗ khác — nên nó cần thêm một cột đánh dấu, một nhánh kiểm tra riêng, và một
     * đoạn giải thích ở mỗi màn hình hiển thị. Nhiều bộ máy cho một tình huống hiếm.
     *
     * Cách còn lại đơn giản hơn và vẫn đúng: phân công thêm một người cho chuyến trước, rồi bàn
     * giao. Luật "đoàn không bị bỏ rơi" giữ nguyên, chỉ mất lối tắt.
     */

    /** Có người thứ hai ở lại với đoàn thì bàn giao được, và người đó vẫn ở lại. */
    public function test_doan_dang_di_co_hai_nguoi_thi_ban_giao_duoc(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/handover', $this->payload())
            ->assertOk();

        $daTai = $this->chuyen->fresh();

        $this->assertFalse($daTai->hasGuide($this->nguoiCu->id));
        $this->assertTrue($daTai->hasGuide($this->nguoiOLai->id), 'Người ở lại vẫn bên đoàn.');
        $this->assertTrue($daTai->hasGuide($this->nguoiMoi->id));
    }

    /**
     * Chuyến chưa khởi hành thì một người cũng đổi được.
     *
     * Đoàn chưa đi nên chưa có gì để bỏ rơi, và người mới còn thời gian tới điểm tập kết.
     */
    public function test_chuyen_chua_khoi_hanh_thi_mot_nguoi_van_doi_duoc(): void
    {
        $chuaDi = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Confirmed->value,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(7),
            'booking_deadline' => now()->addDays(2),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ]);

        $chuaDi->guides()->sync([$this->nguoiCu->id]);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $chuaDi->id . '/handover', $this->payload())
            ->assertOk();

        $this->assertTrue($chuaDi->fresh()->hasGuide($this->nguoiMoi->id));
    }

    public function test_chuyen_da_ket_thuc_thi_khong_ban_giao_duoc(): void
    {
        $xong = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Completed->value,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(8),
            'booking_deadline' => now()->subDays(13),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ]);

        $xong->guides()->sync([$this->nguoiCu->id]);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $xong->id . '/handover', $this->payload())
            ->assertStatus(422);
    }

    public function test_khong_ban_giao_cho_chinh_minh(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->postJson(
            '/api/admin/schedules/' . $this->chuyen->id . '/handover',
            $this->payload(['to_guide_id' => $this->nguoiCu->id]),
        )->assertStatus(422);
    }

    /*
     * Đã gỡ hai bài về bước "người nhận xác nhận đã đọc".
     *
     * Bước ấy không chặn gì: việc chuyển đã xong từ lúc điều hành bấm. Nó chỉ trả lời "người kia
     * biết chưa" — câu mà gọi điện hỏi nhanh hơn là dựng thêm một trạng thái trong cơ sở dữ liệu
     * và một màn hình để bấm.
     */

    // --- Hai phía đều đọc được biên bản ----------------------------------------------------

    /**
     * Người cũ mất quyền ghi nhưng vẫn đọc được vết bàn giao của mình.
     *
     * Không phải để họ can thiệp tiếp, mà để khi có khiếu nại về chặng họ từng dẫn thì còn chỗ
     * đối chiếu mình đã giao lại những gì, lúc nào.
     */
    public function test_ca_nguoi_giao_lan_nguoi_nhan_deu_doc_duoc_bien_ban(): void
    {
        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/handover', $this->payload())
            ->assertOk();

        Sanctum::actingAs($this->nguoiMoi);

        $nhan = $this->getJson('/api/guide/handovers')->assertOk()->json('data');

        $this->assertCount(1, $nhan);
        $this->assertSame('received', $nhan[0]['direction']);
        $this->assertStringContainsString('Bai Chay', $nhan[0]['handover_note']);

        Sanctum::actingAs($this->nguoiCu);

        $giao = $this->getJson('/api/guide/handovers')->assertOk()->json('data');

        $this->assertCount(1, $giao);
        $this->assertSame('given', $giao[0]['direction']);
    }
}
