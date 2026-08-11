<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\TourSchedule;
use App\Services\ScheduleLifecycleService;
use Tests\TestCase;

/**
 * Kiểm thử ScheduleLifecycleService.
 *
 * Phần logic thuần chạy được ngay vì chỉ dùng model chưa lưu, không chạm cơ sở dữ liệu.
 * Phần transitionTo phải chờ migration A01 đổi kiểu cột status, lý do ghi ở từng bài.
 */
class ScheduleLifecycleTest extends TestCase
{
    private ScheduleLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ScheduleLifecycleService();
    }

    private function schedule(array $attributes = []): TourSchedule
    {
        return (new TourSchedule())->forceFill(array_merge([
            'id' => 1,
            'tour_id' => 1,
            'start_date' => now()->addDays(10),
            'max_people' => 20,
            'booked_people' => 0,
            'status' => ScheduleStatus::Open->value,
        ], $attributes));
    }

    public function test_doc_duoc_trang_thai_moi(): void
    {
        $schedule = $this->schedule(['status' => ScheduleStatus::Confirmed->value]);

        $this->assertSame(ScheduleStatus::Confirmed, $this->service->currentStatus($schedule));
    }

    /**
     * Dịch vụ phải chạy được trước khi A01 đổi kiểu cột, nếu không thì không ai kiểm chứng
     * được nó trước lúc migration vào.
     */
    public function test_doc_duoc_ca_gia_tri_cu_truoc_khi_doi_kieu_cot(): void
    {
        $this->assertSame(
            ScheduleStatus::Open,
            $this->service->currentStatus($this->schedule(['status' => 'active'])),
        );

        $this->assertSame(
            ScheduleStatus::Closed,
            $this->service->currentStatus($this->schedule(['status' => 'full'])),
        );

        $this->assertSame(
            ScheduleStatus::Cancelled,
            $this->service->currentStatus($this->schedule(['status' => 'inactive'])),
        );
    }

    public function test_gia_tri_cu_cua_chuyen_da_khoi_hanh_doc_thanh_da_ket_thuc(): void
    {
        $schedule = $this->schedule([
            'status' => 'active',
            'start_date' => now()->subDays(5),
        ]);

        $this->assertSame(ScheduleStatus::Completed, $this->service->currentStatus($schedule));
    }

    public function test_chuyen_ve_chinh_trang_thai_dang_co_thi_bo_qua(): void
    {
        $this->service->assertCanTransition(ScheduleStatus::Open, ScheduleStatus::Open);

        $this->addToAssertionCount(1);
    }

    public function test_duong_chuyen_khong_hop_le_bi_nem_loi(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->service->assertCanTransition(ScheduleStatus::InProgress, ScheduleStatus::Cancelled);
    }

    public function test_thong_bao_loi_neu_ro_hai_trang_thai(): void
    {
        try {
            $this->service->assertCanTransition(ScheduleStatus::Completed, ScheduleStatus::Open);
            $this->fail('Lẽ ra phải ném BusinessRuleException.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString(ScheduleStatus::Completed->label(), $e->getMessage());
            $this->assertStringContainsString(ScheduleStatus::Open->label(), $e->getMessage());
            $this->assertSame(422, $e->status());
        }
    }

    public function test_chuyen_da_chot_va_toi_gio_thi_chuyen_sang_dang_chay(): void
    {
        $schedule = $this->schedule([
            'status' => ScheduleStatus::Confirmed->value,
            'start_date' => now()->subHour(),
            'end_date' => now()->addDays(2),
        ]);

        $this->assertSame(ScheduleStatus::InProgress, $this->service->resolveStatusByTime($schedule));
    }

    public function test_chuyen_da_chot_nhung_chua_toi_gio_thi_giu_nguyen(): void
    {
        $schedule = $this->schedule([
            'status' => ScheduleStatus::Confirmed->value,
            'start_date' => now()->addDays(3),
            'end_date' => now()->addDays(5),
        ]);

        $this->assertNull($this->service->resolveStatusByTime($schedule));
    }

    public function test_chuyen_dang_chay_qua_ngay_ket_thuc_thi_hoan_thanh(): void
    {
        $schedule = $this->schedule([
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subDays(4),
            'end_date' => now()->subDay(),
        ]);

        $this->assertSame(ScheduleStatus::Completed, $this->service->resolveStatusByTime($schedule));
    }

    public function test_chuyen_da_huy_khong_bi_lenh_nen_dong_vao(): void
    {
        $schedule = $this->schedule([
            'status' => ScheduleStatus::Cancelled->value,
            'start_date' => now()->subDays(4),
            'end_date' => now()->subDay(),
        ]);

        $this->assertNull($this->service->resolveStatusByTime($schedule));
    }

    public function test_chuyen_da_ket_thuc_khong_bi_dong_vao_nua(): void
    {
        $schedule = $this->schedule([
            'status' => ScheduleStatus::Completed->value,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(8),
        ]);

        $this->assertNull($this->service->resolveStatusByTime($schedule));
    }

    /**
     * Chưa có cột end_date trước migration A01, khi đó ngày kết thúc suy từ số ngày của tour.
     * Bài này giữ lại cả sau A01 vì chuyến cũ vẫn có thể để trống end_date.
     */
    public function test_thieu_ngay_ket_thuc_thi_suy_tu_so_ngay_cua_tour(): void
    {
        $schedule = $this->schedule([
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subDays(5),
            'end_date' => null,
        ]);

        $schedule->setRelation('tour', new \App\Models\Tour(['number_of_days' => 3]));

        $this->assertSame(ScheduleStatus::Completed, $this->service->resolveStatusByTime($schedule));
    }

    public function test_ghi_du_cot_truy_vet_khi_huy_chuyen(): void
    {
        $this->markTestSkipped(
            'Cần migration A01 đổi cột status sang string và thêm cancelled_at, cancelled_by, '
            . 'cancelled_reason. Trước đó SQLite còn CHECK constraint chỉ nhận active/inactive/full.'
        );
    }

    public function test_hai_luong_cung_doi_trang_thai_thi_luong_sau_bi_tu_choi(): void
    {
        $this->markTestSkipped(
            'Cần migration A01. Bài này mở hai giao dịch song song trên cùng một chuyến để '
            . 'kiểm chứng lockForUpdate trong transitionTo, chỉ chạy được khi cột status đã đổi kiểu.'
        );
    }
}
