<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Notifications\Alert;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Hộp thông báo của điều hành và của hướng dẫn viên.
 *
 * Hai chiều dùng chung một đường ống: cùng bảng `notifications`, cùng lớp `Alert`, cùng bốn điểm
 * cuối `/api/notifications`. Khác nhau chỉ ở chỗ ai được gửi cái gì, nên bộ test này kiểm cả hai
 * chiều trong một tệp - tách ra thì nửa còn lại dễ bị bỏ quên khi sửa đường ống.
 *
 * **Điều bộ test này giữ: thông báo được ghi vào cơ sở dữ liệu, không phụ thuộc WebSocket.**
 *
 * Ở môi trường test `BROADCAST_CONNECTION=null`, tức không có đường đẩy tức thì nào — đúng tình
 * huống lúc quên bật Reverb. Mọi bài dưới đây vẫn phải xanh. Nếu ai đó về sau đổi sang chỉ gửi
 * qua broadcast cho gọn thì cả bộ này đỏ, vì đó là lúc tính năng chết lặng khi thiếu một tiến
 * trình nền.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private User $dieuHanhHai;
    private User $guide;
    private Tour $tour;
    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dieuHanh = $this->taoNguoi('admin');
        $this->dieuHanhHai = $this->taoNguoi('admin');
        $this->guide = $this->taoNguoi('guide');

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 2,
        ]);

        $this->chuyen = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(11),
            'booking_deadline' => now()->addDays(7),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ]);

        $this->chuyen->guides()->sync([$this->guide->id]);
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

    private function tuChoiChuyen(): void
    {
        Sanctum::actingAs($this->guide);

        $this->putJson('/api/guide/assignments/' . $this->chuyen->id . '/decline', [
            'reason' => 'Toi bi sot cao, khong dan duoc chuyen nay.',
        ])->assertOk();
    }

    // --- Sinh thông báo -------------------------------------------------------------------

    /**
     * Bài trung tâm: hướng dẫn viên từ chối thì điều hành biết ngay.
     *
     * Trước đây họ chỉ phát hiện khi tự mở màn quản lý chuyến và thấy thẻ hướng dẫn viên trống —
     * mà càng biết muộn thì càng ít lựa chọn để xếp người thay.
     */
    public function test_huong_dan_vien_tu_choi_thi_dieu_hanh_nhan_thong_bao(): void
    {
        $this->tuChoiChuyen();

        $this->assertSame(
            1,
            $this->dieuHanh->unreadNotifications()->count(),
            'Thông báo phải nằm trong cơ sở dữ liệu kể cả khi không có WebSocket.',
        );

        $data = $this->dieuHanh->notifications()->first()->data;

        $this->assertSame(Alert::TU_CHOI_CHUYEN, $data['kind']);
        $this->assertStringContainsString($this->guide->name, $data['title']);
        $this->assertStringContainsString('sot cao', $data['body'], 'Lý do họ viết phải đi kèm.');
        $this->assertSame('/admin/schedules', $data['url']);
    }

    /** Mọi điều hành đều nhận, không chỉ một người. */
    public function test_moi_dieu_hanh_deu_nhan_duoc(): void
    {
        $this->tuChoiChuyen();

        $this->assertSame(1, $this->dieuHanh->unreadNotifications()->count());
        $this->assertSame(1, $this->dieuHanhHai->unreadNotifications()->count());
    }

    /** Hướng dẫn viên và khách không nhận thông báo điều hành. */
    public function test_huong_dan_vien_khong_nhan_thong_bao_dieu_hanh(): void
    {
        $this->tuChoiChuyen();

        $this->assertSame(0, $this->guide->unreadNotifications()->count());
    }

    /**
     * Tài khoản điều hành đã khóa thì không nhận.
     *
     * Thông báo mang tên hướng dẫn viên, lý do họ viết và tình trạng đoàn. Người đã bị khóa quyền
     * không nên tiếp tục đọc những thứ đó.
     */
    public function test_dieu_hanh_da_khoa_thi_khong_nhan(): void
    {
        $this->dieuHanhHai->forceFill(['status' => 'inactive'])->save();

        $this->tuChoiChuyen();

        $this->assertSame(0, $this->dieuHanhHai->unreadNotifications()->count());
    }

    // --- Đọc và đánh dấu ------------------------------------------------------------------

    public function test_doc_duoc_danh_sach_va_so_chua_doc(): void
    {
        $this->tuChoiChuyen();

        Sanctum::actingAs($this->dieuHanh);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonCount(1, 'data.notifications')
            ->assertJsonPath('data.notifications.0.kind', Alert::TU_CHOI_CHUYEN);
    }

    /** Điểm cuối đếm riêng: màn hình hỏi nó định kỳ khi không có WebSocket. */
    public function test_diem_cuoi_dem_rieng_tra_ve_dung_so(): void
    {
        $this->tuChoiChuyen();

        Sanctum::actingAs($this->dieuHanh);

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);
    }

    public function test_danh_dau_da_doc_mot_thong_bao(): void
    {
        $this->tuChoiChuyen();

        Sanctum::actingAs($this->dieuHanh);

        $id = $this->dieuHanh->notifications()->first()->id;

        $this->putJson('/api/notifications/' . $id . '/read')->assertOk();

        $this->assertSame(0, $this->dieuHanh->fresh()->unreadNotifications()->count());
    }

    public function test_danh_dau_tat_ca_da_doc(): void
    {
        $this->tuChoiChuyen();

        // Thêm một việc nữa để chắc chắn là đánh dấu hết chứ không phải chỉ cái đầu.
        $guideHai = $this->taoNguoi('guide');
        $this->chuyen->guides()->attach($guideHai->id);

        Sanctum::actingAs($guideHai);
        $this->putJson('/api/guide/assignments/' . $this->chuyen->id . '/decline', [
            'reason' => 'Toi cung ban hom do, xin phep khong nhan.',
        ])->assertOk();

        $this->assertSame(2, $this->dieuHanh->fresh()->unreadNotifications()->count());

        Sanctum::actingAs($this->dieuHanh);
        $this->putJson('/api/notifications/read-all')->assertOk();

        $this->assertSame(0, $this->dieuHanh->fresh()->unreadNotifications()->count());
    }

    /** Không đọc được thông báo của người khác, kể cả khi biết id. */
    public function test_khong_danh_dau_duoc_thong_bao_cua_nguoi_khac(): void
    {
        $this->tuChoiChuyen();

        $cuaNguoiKhac = $this->dieuHanhHai->notifications()->first()->id;

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/notifications/' . $cuaNguoiKhac . '/read')->assertStatus(404);

        $this->assertSame(1, $this->dieuHanhHai->fresh()->unreadNotifications()->count());
    }

    /**
     * Hộp thông báo mở cho điều hành và hướng dẫn viên, chưa mở cho khách.
     *
     * Không phải vì khách nguy hiểm — mà vì chưa có thông báo nào gửi cho họ, và mở một hộp trống
     * ra thì phải trả lời tiếp câu "khách được báo những gì". Đó là một tính năng khác.
     */
    public function test_khach_khong_goi_duoc_api_thong_bao(): void
    {
        Sanctum::actingAs($this->taoNguoi('customer'));

        $this->getJson('/api/notifications')->assertForbidden();
    }

    // --- Sự cố: chỉ mức nghiêm trọng mới báo động ------------------------------------------

    private function baoSuCo(string $severity): void
    {
        $dangChay = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'booking_deadline' => now()->subDays(4),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ]);

        $dangChay->guides()->sync([$this->guide->id]);

        Sanctum::actingAs($this->guide);

        $this->postJson('/api/guide/schedules/' . $dangChay->id . '/incidents', [
            'type' => 'weather',
            'severity' => $severity,
            'occurred_at' => now()->subHour()->toDateTimeString(),
            'description' => 'Bao vao dat lien, tau khong ra dao duoc, doan phai o lai bo.',
        ])->assertOk();
    }

    public function test_su_co_nghiem_trong_thi_bao_dong(): void
    {
        $this->baoSuCo('high');

        $this->assertSame(1, $this->dieuHanh->unreadNotifications()->count());
        $this->assertSame(
            Alert::SU_CO,
            $this->dieuHanh->notifications()->first()->data['kind'],
        );
    }

    /**
     * Sự cố nhẹ thì không báo động.
     *
     * Bắn chuông cho mọi sự cố thì sau một tuần không ai nhìn chuông nữa, và lúc bão thật nó cũng
     * chỉ là một dòng như mọi dòng khác. Sự cố nhẹ vẫn nằm trong danh sách chờ xử lý.
     */
    public function test_su_co_nhe_thi_khong_bao_dong(): void
    {
        $this->baoSuCo('low');

        $this->assertSame(0, $this->dieuHanh->unreadNotifications()->count());
    }

    // --- Chiều ngược lại: hướng dẫn viên cũng được báo -------------------------------------

    /**
     * Được phân công thì biết ngay, không phải tự mở danh sách ra dò.
     *
     * Đây là chiều đối xứng của việc từ chối: trước đây cả hai phía đều chỉ phát hiện bằng cách
     * tự mở màn hình của mình.
     */
    public function test_duoc_phan_cong_thi_huong_dan_vien_nhan_thong_bao(): void
    {
        $nguoiMoi = $this->taoNguoi('guide');

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/tour-schedules/' . $this->chuyen->id . '/assign-guide', [
            'guide_ids' => [$this->guide->id, $nguoiMoi->id],
        ])->assertOk();

        $this->assertSame(1, $nguoiMoi->unreadNotifications()->count());

        $data = $nguoiMoi->notifications()->first()->data;
        $this->assertSame(Alert::PHAN_CONG, $data['kind']);
        $this->assertSame('/guide/assignments', $data['url']);
    }

    /**
     * Người vốn đã ở trong chuyến thì KHÔNG bị báo lại.
     *
     * Điều hành sửa danh sách vì nhiều lý do. Bắn lại thông báo cho những ai không đổi gì là cách
     * nhanh nhất để họ ngừng đọc thông báo.
     */
    public function test_nguoi_da_o_trong_chuyen_khong_bi_bao_lai(): void
    {
        $nguoiMoi = $this->taoNguoi('guide');

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/tour-schedules/' . $this->chuyen->id . '/assign-guide', [
            'guide_ids' => [$this->guide->id, $nguoiMoi->id],
        ])->assertOk();

        $this->assertSame(
            0,
            $this->guide->unreadNotifications()->count(),
            'Người đã phụ trách từ trước không có gì mới để biết.',
        );
    }

    /**
     * Nhận bàn giao là thông báo gấp nhất phía hướng dẫn viên.
     *
     * Họ vừa nhận một đoàn có thể đang trên đường, và đoạn ghi chú tình trạng đoàn đi kèm chính
     * là thứ họ cần để bắt nhịp.
     */
    public function test_nhan_ban_giao_thi_nguoi_nhan_duoc_bao_kem_tinh_trang_doan(): void
    {
        $dangChay = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'booking_deadline' => now()->subDays(4),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ]);

        $nguoiOLai = $this->taoNguoi('guide');
        $nguoiNhan = $this->taoNguoi('guide');

        $dangChay->guides()->sync([$this->guide->id, $nguoiOLai->id]);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $dangChay->id . '/handover', [
            'from_guide_id' => $this->guide->id,
            'to_guide_id' => $nguoiNhan->id,
            'reason' => 'Nguoi dan cu bi sot cao, khong tiep tuc duoc.',
            'handover_note' => 'Doan dang o Bai Chay, da diem danh xong chang mot. Chieu con di thuyen.',
        ])->assertOk();

        $data = $nguoiNhan->notifications()->first()?->data;

        $this->assertNotNull($data, 'Người nhận đoàn phải được báo.');
        $this->assertSame(Alert::NHAN_BAN_GIAO, $data['kind']);
        $this->assertStringContainsString('Bai Chay', $data['body']);
    }

    /**
     * Người nhận lấy từ quan hệ nạp thiếu cột thì vẫn phải nhận được thông báo.
     *
     * `Notifier` bỏ qua tài khoản đã ngừng hoạt động. Nhưng nơi gọi hay lấy người nhận từ
     * `with('toGuide:id,name,phone')` — không có `status` — nên phép kiểm ấy đọc phải null và
     * lặng lẽ nuốt mọi thông báo. Bài này chốt lại: thiếu cột không được biến thành mất thông báo.
     */
    public function test_nguoi_nhan_nap_thieu_cot_van_duoc_bao(): void
    {
        $nguoi = $this->taoNguoi('guide');
        $chiCoTen = User::query()->select('id', 'name')->findOrFail($nguoi->id);

        app(\App\Services\Notifier::class)->toiNguoiDung(
            $chiCoTen,
            Alert::PHAN_CONG,
            'Tieu de',
            'Noi dung',
        );

        $this->assertSame(1, $nguoi->unreadNotifications()->count());
    }

    /** Tài khoản đã ngừng hoạt động thì thôi — phép kiểm ấy vẫn phải còn tác dụng. */
    public function test_tai_khoan_ngung_hoat_dong_thi_khong_gui(): void
    {
        $nguoi = $this->taoNguoi('guide');
        $nguoi->update(['status' => 'inactive']);

        app(\App\Services\Notifier::class)->toiNguoiDung(
            $nguoi->fresh(),
            Alert::PHAN_CONG,
            'Tieu de',
            'Noi dung',
        );

        $this->assertSame(0, $nguoi->unreadNotifications()->count());
    }

    /** Đóng phiếu thì người gửi phải biết, kể cả khi không đổi người. */
    public function test_dong_phieu_thi_nguoi_gui_duoc_bao(): void
    {
        $dangChay = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'booking_deadline' => now()->subDays(4),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ]);

        $dangChay->guides()->sync([$this->guide->id]);

        Sanctum::actingAs($this->guide);
        $this->postJson('/api/guide/schedules/' . $dangChay->id . '/handover-request', [
            'reason' => 'Toi bi sot cao, khong dan tiep duoc.',
            'group_state' => 'Doan dang o Bai Chay, da diem danh xong chang mot, chieu con di thuyen.',
        ])->assertOk();

        $phieu = \App\Models\GuideHandoverRequest::query()->latest('id')->firstOrFail();

        Sanctum::actingAs($this->dieuHanh);
        $this->putJson('/api/admin/handover-requests/' . $phieu->id . '/close', [
            'review_note' => 'Khong con ai ranh hom nay, ban co gang giup den chieu.',
        ])->assertOk();

        $data = $this->guide->notifications()
            ->where('type', Alert::class)
            ->get()
            ->firstWhere(fn ($tb) => $tb->data['kind'] === Alert::PHIEU_DA_XU_LY)
            ?->data;

        $this->assertNotNull($data, 'Người xin giúp phải biết câu trả lời.');
        $this->assertStringContainsString('co gang giup den chieu', $data['body']);
    }
}
