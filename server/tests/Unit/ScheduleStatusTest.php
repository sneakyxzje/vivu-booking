<?php

namespace Tests\Unit;

use App\Enums\ScheduleStatus;
use PHPUnit\Framework\TestCase;

/**
 * Bảng chuyển trạng thái là quy tắc nghiệp vụ thuần, không chạm cơ sở dữ liệu,
 * nên kiểm thử ở đây chạy được ngay cả trước khi migration A01 vào.
 */
class ScheduleStatusTest extends TestCase
{
    /**
     * Bảng chuyển hợp lệ, chép đúng từ docs/nghiep-vu/01-tac-nhan-va-vong-doi.md mục 4.
     *
     * @return array<string, array<int, string>>
     */
    private function expectedMatrix(): array
    {
        return [
            'open' => ['closed', 'confirmed', 'cancelled'],
            'closed' => ['open', 'confirmed', 'cancelled'],
            'confirmed' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed'],
            'completed' => [],
            'cancelled' => [],
        ];
    }

    public function test_moi_duong_chuyen_hop_le_deu_di_duoc(): void
    {
        foreach ($this->expectedMatrix() as $from => $allowed) {
            $fromStatus = ScheduleStatus::from($from);

            foreach ($allowed as $to) {
                $this->assertTrue(
                    $fromStatus->canTransitionTo(ScheduleStatus::from($to)),
                    "Phải chuyển được từ {$from} sang {$to}.",
                );
            }
        }
    }

    public function test_moi_duong_chuyen_ngoai_bang_deu_bi_tu_choi(): void
    {
        foreach ($this->expectedMatrix() as $from => $allowed) {
            $fromStatus = ScheduleStatus::from($from);

            foreach (ScheduleStatus::cases() as $to) {
                if ($to->value === $from || in_array($to->value, $allowed, true)) {
                    continue;
                }

                $this->assertFalse(
                    $fromStatus->canTransitionTo($to),
                    "Không được phép chuyển từ {$from} sang {$to->value}.",
                );
            }
        }
    }

    /**
     * Ràng buộc nghiệp vụ quan trọng nhất của cả máy trạng thái: chuyến đã khởi hành thì
     * không có đường hủy. Xem docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 4.
     */
    public function test_chuyen_dang_chay_khong_the_bi_huy(): void
    {
        $this->assertFalse(ScheduleStatus::InProgress->canTransitionTo(ScheduleStatus::Cancelled));
        $this->assertSame([ScheduleStatus::Completed], ScheduleStatus::InProgress->allowedTransitions());
    }

    public function test_trang_thai_ket_thuc_khong_di_tiep_duoc(): void
    {
        $this->assertTrue(ScheduleStatus::Completed->isFinal());
        $this->assertTrue(ScheduleStatus::Cancelled->isFinal());
        $this->assertFalse(ScheduleStatus::Open->isFinal());
        $this->assertFalse(ScheduleStatus::InProgress->isFinal());
    }

    public function test_dong_ban_co_the_mo_lai_khi_co_khach_huy(): void
    {
        $this->assertTrue(ScheduleStatus::Closed->canTransitionTo(ScheduleStatus::Open));
    }

    public function test_chi_chuyen_dang_mo_ban_moi_nhan_dat_cho(): void
    {
        $this->assertTrue(ScheduleStatus::Open->isBookable());

        foreach (ScheduleStatus::cases() as $status) {
            if ($status === ScheduleStatus::Open) {
                continue;
            }

            $this->assertFalse($status->isBookable(), "{$status->value} không được nhận đặt chỗ.");
        }
    }

    public function test_chan_huy_don_khi_chuyen_dang_chay_hoac_da_ket_thuc(): void
    {
        $this->assertTrue(ScheduleStatus::InProgress->blocksCancellation());
        $this->assertTrue(ScheduleStatus::Completed->blocksCancellation());

        $this->assertFalse(ScheduleStatus::Open->blocksCancellation());
        $this->assertFalse(ScheduleStatus::Closed->blocksCancellation());
        $this->assertFalse(ScheduleStatus::Confirmed->blocksCancellation());
        $this->assertFalse(ScheduleStatus::Cancelled->blocksCancellation());
    }

    public function test_quy_doi_gia_tri_cu_cua_chuyen_chua_khoi_hanh(): void
    {
        $this->assertSame(ScheduleStatus::Open, ScheduleStatus::fromLegacy('active'));
        $this->assertSame(ScheduleStatus::Closed, ScheduleStatus::fromLegacy('full'));
        $this->assertSame(ScheduleStatus::Cancelled, ScheduleStatus::fromLegacy('inactive'));
    }

    public function test_chuyen_da_qua_ngay_khoi_hanh_thi_quy_doi_thanh_da_ket_thuc(): void
    {
        $this->assertSame(ScheduleStatus::Completed, ScheduleStatus::fromLegacy('active', true));
        $this->assertSame(ScheduleStatus::Completed, ScheduleStatus::fromLegacy('full', true));
        $this->assertSame(ScheduleStatus::Completed, ScheduleStatus::fromLegacy('inactive', true));
    }

    public function test_quy_doi_gia_tri_moi_giu_nguyen(): void
    {
        foreach (ScheduleStatus::cases() as $status) {
            $this->assertSame($status, ScheduleStatus::fromLegacy($status->value));
        }
    }

    public function test_gia_tri_la_khong_lam_vo_ung_dung(): void
    {
        $this->assertSame(ScheduleStatus::Open, ScheduleStatus::fromLegacy('gia_tri_khong_ton_tai'));
    }

    public function test_values_tra_ve_dung_sau_trang_thai(): void
    {
        $this->assertSame(
            ['open', 'closed', 'confirmed', 'in_progress', 'completed', 'cancelled'],
            ScheduleStatus::values(),
        );
    }

    public function test_moi_trang_thai_deu_co_nhan_tieng_viet(): void
    {
        foreach (ScheduleStatus::cases() as $status) {
            $this->assertNotSame('', trim($status->label()));
        }
    }
}
