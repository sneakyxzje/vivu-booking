<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\BookingPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Câu hỏi số 9 của hội đồng: tour đang chạy thì có được hủy không.
 * Quy tắc và lý do ở docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 4.
 *
 * Điểm cần chứng minh là quy tắc nằm ở tầng dịch vụ nên chặn được MỌI lối vào,
 * chứ không phải chặn riêng ở màn hình nào.
 */
class BookingCancellationGuardTest extends TestCase
{
    use RefreshDatabase;

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

    private function taoDon(string $khoiHanh, string $trangThaiDon = 'pending', ?User $khach = null): Booking
    {
        $tour = Tour::create([
            'admin_id' => $this->taoUser('admin')->id,
            'title' => 'Tour Test Huy Don',
            'slug' => 'tour-test-huy-don-' . Str::random(6),
            'adult_price' => 1000000,
            'child_price' => 700000,
            'infant_price' => 0,
            'number_of_days' => 2,
            'number_of_nights' => 1,
            'start_location' => 'Ha Noi',
            'status' => 'active',
        ]);

        $schedule = TourSchedule::create([
            'tour_id' => $tour->id,
            'start_date' => $khoiHanh,
            'max_people' => 10,
            'booked_people' => 2,
            'status' => 'open',
        ]);

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $schedule->id,
            'customer_id' => $khach?->id,
            'customer_name' => 'Khach Test',
            'customer_email' => 'khach@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 2000000,
            'status' => $trangThaiDon,
            'expires_at' => $trangThaiDon === 'pending' ? now()->addMinutes(10) : null,
        ]);
    }

    public function test_quan_tri_khong_huy_duoc_don_cua_chuyen_da_khoi_hanh(): void
    {
        $don = $this->taoDon(khoiHanh: now()->subDays(3)->toDateTimeString(), trangThaiDon: 'confirmed');
        Sanctum::actingAs($this->taoUser('admin'));

        $this->putJson("/api/admin/bookings/{$don->id}/cancel", [
            'cancel_reason' => 'Khach doi y sau khi doan da di',
        ])
            ->assertStatus(422)
            // Khẳng định vào nội dung thông báo vì 422 cũng là mã của lỗi validation.
            // Không có dòng này thì bài test vẫn xanh kể cả khi guard không chạy.
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Chuyến đi đã kết thúc nên không thể hủy đơn. '
                . 'Vui lòng liên hệ điều hành nếu cần khiếu nại hoặc yêu cầu hoàn tiền.']);

        $this->assertSame('confirmed', $don->fresh()->status);
        $this->assertSame(2, (int) $don->schedule->fresh()->booked_people);
    }

    public function test_khach_khong_huy_duoc_don_cua_chuyen_da_khoi_hanh(): void
    {
        $khach = $this->taoUser('customer');
        $don = $this->taoDon(khoiHanh: now()->subDay()->toDateTimeString(), khach: $khach);
        Sanctum::actingAs($khach);

        // Tour 2 ngày khởi hành từ hôm qua nên hôm nay đoàn vẫn đang đi, thông báo phải là
        // "đã khởi hành" chứ không phải "đã kết thúc".
        $this->putJson("/api/my-bookings/{$don->id}/cancel", [
            'cancel_reason' => 'Khong di nua',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Chuyến đi đã khởi hành nên không thể hủy đơn. '
                . 'Vui lòng liên hệ điều hành để ghi nhận khách vắng mặt hoặc rời đoàn giữa chừng.']);

        $this->assertSame('pending', $don->fresh()->status);
    }

    /**
     * Chuyến chưa khởi hành thì luồng hủy cũ phải chạy y như trước.
     * Bài này để chắc chắn guard mới không chặn nhầm.
     */
    public function test_van_huy_duoc_binh_thuong_khi_chuyen_chua_khoi_hanh(): void
    {
        $don = $this->taoDon(khoiHanh: now()->addDays(7)->toDateTimeString(), trangThaiDon: 'confirmed');
        Sanctum::actingAs($this->taoUser('admin'));

        $this->putJson("/api/admin/bookings/{$don->id}/cancel", [
            'cancel_reason' => 'Khach yeu cau huy truoc ngay di',
        ])->assertOk();

        $this->assertSame('cancelled', $don->fresh()->status);
        $this->assertSame(0, (int) $don->schedule->fresh()->booked_people);
    }

    public function test_huy_don_da_huy_van_tra_ve_400(): void
    {
        $don = $this->taoDon(khoiHanh: now()->addDays(7)->toDateTimeString(), trangThaiDon: 'cancelled');
        Sanctum::actingAs($this->taoUser('admin'));

        $this->putJson("/api/admin/bookings/{$don->id}/cancel", [
            'cancel_reason' => 'Huy lai lan nua',
        ])->assertStatus(400);
    }

    /**
     * Chuyến đang chạy chỉ tạo được bằng model chưa lưu, vì cột status của cơ sở dữ liệu
     * còn là enum ba giá trị cũ cho tới khi migration A01 vào.
     */
    public function test_dich_vu_chan_ca_chuyen_dang_chay_lan_da_ket_thuc(): void
    {
        $policy = app(BookingPolicyService::class);

        foreach ([ScheduleStatus::InProgress, ScheduleStatus::Completed] as $status) {
            $schedule = (new TourSchedule())->forceFill([
                'id' => 1,
                'status' => $status->value,
                'start_date' => now()->subDay(),
            ]);

            try {
                $policy->assertScheduleAllowsCancellation($schedule);
                $this->fail("Phải chặn hủy đơn khi chuyến ở trạng thái {$status->value}.");
            } catch (BusinessRuleException $e) {
                $this->assertSame(422, $e->status());
            }
        }
    }

    public function test_dich_vu_khong_chan_chuyen_chua_khoi_hanh(): void
    {
        $policy = app(BookingPolicyService::class);

        foreach ([ScheduleStatus::Open, ScheduleStatus::Closed, ScheduleStatus::Confirmed] as $status) {
            $schedule = (new TourSchedule())->forceFill([
                'id' => 1,
                'status' => $status->value,
                'start_date' => now()->addDays(5),
            ]);

            $policy->assertScheduleAllowsCancellation($schedule);
        }

        $this->addToAssertionCount(1);
    }

    public function test_dich_vu_chan_moi_trang_thai_don_da_ket_thuc(): void
    {
        $policy = app(BookingPolicyService::class);

        foreach (BookingStatus::terminalValues() as $value) {
            $booking = (new Booking())->forceFill(['status' => $value]);

            try {
                $policy->assertCancellable($booking);
                $this->fail("Phải chặn hủy đơn đang ở trạng thái {$value}.");
            } catch (BusinessRuleException $e) {
                $this->assertSame(400, $e->status());
            }
        }
    }

    public function test_don_khong_gan_chuyen_thi_khong_bi_chan_boi_lich_khoi_hanh(): void
    {
        $policy = app(BookingPolicyService::class);
        $booking = (new Booking())->forceFill(['status' => 'pending', 'tour_schedule_id' => null]);

        $policy->assertCancellable($booking);

        $this->addToAssertionCount(1);
    }
}

