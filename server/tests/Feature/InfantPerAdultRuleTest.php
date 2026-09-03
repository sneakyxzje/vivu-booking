<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Mỗi em bé phải có một người lớn đi kèm.
 *
 * ## Vì sao luật này cần tồn tại
 *
 * Em bé dưới hai tuổi không chiếm ghế — bé ngồi lòng bố mẹ — nên kho chỗ của chuyến cố ý không đếm
 * các cháu. Mô hình ấy đúng với xe khách, và nó là lý do một gia đình hai người lớn kèm một em bé
 * đặt được chuyến chỉ còn hai chỗ.
 *
 * Nhưng nếu chỉ có mình nó thì mở ra một đơn không thể tồn tại ngoài đời: một người lớn đi cùng tám
 * em bé chiếm đúng MỘT ghế và trả đúng MỘT vé, vì vé em bé bằng không. Không ai bế được tám đứa
 * trẻ, và nếu có thì công ty vẫn phải chở chín người bằng tiền của một.
 *
 * Luật một-lòng-một-bé chặn đúng chỗ vô lý ấy mà không phải phá mô hình ghế.
 */
class InfantPerAdultRuleTest extends TestCase
{
    use RefreshDatabase;

    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->seed(\Database\Seeders\CancellationPolicySeeder::class);

        $tour = Tour::factory()->create([
            'status' => 'active',
            'adult_price' => 2_000_000,
            'child_price' => 1_400_000,
            'infant_price' => 0,
        ]);

        $start = now()->addDays(30);

        $this->chuyen = TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 0,
        ]);
    }

    /** @param array<string, mixed> $ghiDe */
    private function datTour(array $ghiDe = [])
    {
        return $this->postJson('/api/bookings', array_merge([
            'tour_id' => $this->chuyen->tour_id,
            'tour_schedule_id' => $this->chuyen->id,
            'customer_name' => 'Nguyen Van A',
            'customer_email' => 'a@example.com',
            'customer_phone' => '0901234567',
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'accept_terms' => true,
        ], $ghiDe));
    }

    /** Một người lớn kèm tám em bé bị từ chối, kèm câu giải thích đọc được. */
    public function test_chan_mot_nguoi_lon_kem_tam_em_be(): void
    {
        $this->datTour(['adult_count' => 1, 'infant_count' => 8])
            ->assertStatus(422)
            ->assertJsonValidationErrors('infant_count');

        $this->assertSame(0, Booking::query()->count(), 'Không đơn nào được tạo.');
    }

    /** Số em bé bằng số người lớn thì được — đó là trần, không phải quá trần. */
    public function test_so_em_be_bang_so_nguoi_lon_thi_duoc(): void
    {
        $this->datTour(['adult_count' => 2, 'infant_count' => 2])->assertCreated();

        $don = Booking::query()->firstOrFail();

        $this->assertSame(2, (int) $don->adult_count);
        $this->assertSame(2, (int) $don->infant_count);
    }

    /**
     * Và đơn ấy chỉ chiếm HAI ghế, không phải bốn.
     *
     * Đây là nửa còn lại của mô hình: luật một-lòng-một-bé chặn số lượng, còn phép đếm ghế vẫn để
     * em bé ra ngoài. Bỏ mất vế này là quay về đếm đầu người, và xe ba mươi chỗ bán được ít hơn ba
     * mươi vé.
     */
    public function test_hai_nguoi_lon_hai_em_be_chi_chiem_hai_ghe(): void
    {
        $this->datTour(['adult_count' => 2, 'infant_count' => 2])->assertCreated();

        $don = Booking::query()->firstOrFail();

        $this->assertSame(2, (int) $don->seats, 'Ghế đếm người lớn và trẻ em, không đếm em bé.');
        $this->assertSame(4, (int) $don->guests, 'Nhưng số NGƯỜI đi vẫn là bốn.');
        $this->assertSame(2, (int) $this->chuyen->fresh()->booked_people, 'Kho chỗ trừ theo ghế.');
    }

    /** Trẻ em thì khác em bé: các cháu CÓ chiếm ghế, nên không bị luật này ràng. */
    public function test_tre_em_khong_bi_rang_boi_luat_nay(): void
    {
        $this->datTour(['adult_count' => 1, 'child_count' => 5, 'infant_count' => 1])
            ->assertCreated();

        $don = Booking::query()->firstOrFail();

        $this->assertSame(6, (int) $don->seats, 'Một người lớn cộng năm trẻ em là sáu ghế.');
    }

    /** Không có em bé thì luật không chạm tới gì. */
    public function test_khong_co_em_be_thi_khong_anh_huong(): void
    {
        $this->datTour(['adult_count' => 1, 'infant_count' => 0])->assertCreated();

        $this->assertSame(1, Booking::query()->count());
    }
}
