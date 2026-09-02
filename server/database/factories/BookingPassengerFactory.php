<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Services\PassengerPolicyService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPassenger>
 *
 * Một hành khách trong danh sách đoàn.
 *
 * Ngày sinh luôn được đặt khớp với `type`. `PassengerPolicyService::validateList()` từ chối lưu khi
 * hai thứ đó nói khác nhau, nên một hành khách dựng sẵn mà lệch tuổi sẽ chặn đúng thao tác mà người
 * thử tay định thử: mở danh sách ra sửa một cái tên.
 */
class BookingPassengerFactory extends Factory
{
    protected $model = BookingPassenger::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            // Viết hoa như danh sách gửi nhà cung cấp, để nhìn màn hình là biết đây là bản đã khai.
            'name' => mb_strtoupper($this->faker->name()),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'type' => 'adult',
            'date_of_birth' => now()->subYears(32)->toDateString(),
            // Căn cước 12 số.
            'identity_number' => $this->faker->unique()->numerify('0###########'),
            'id_type' => 'cccd',
            'nationality' => 'Việt Nam',
            'phone' => '09' . $this->faker->numerify('########'),
            'is_contact' => false,
            'note' => null,
        ];
    }

    public function nguoiLon(int $tuoi = 32): static
    {
        return $this->state(fn (): array => [
            'type' => 'adult',
            'date_of_birth' => now()->subYears(max($tuoi, PassengerPolicyService::ADULT_FROM_AGE))->toDateString(),
        ]);
    }

    public function treEm(int $tuoi = 8): static
    {
        // Kẹp trong khoảng [2, 12): dưới là em bé, từ 12 trở lên là người lớn.
        $tuoi = min(max($tuoi, PassengerPolicyService::INFANT_UNDER_AGE), PassengerPolicyService::ADULT_FROM_AGE - 1);

        // Không giấy tờ, không điện thoại: biểu mẫu khai hành khách cũng không hỏi trẻ em hai
        // thứ ấy, nên dữ liệu dựng sẵn phải giống thứ luồng thật sinh ra.
        return $this->state(fn (): array => [
            'type' => 'child',
            'date_of_birth' => now()->subYears($tuoi)->toDateString(),
            'identity_number' => null,
            'id_type' => null,
            'phone' => null,
        ]);
    }

    /** Người hướng dẫn viên gọi khi cần liên lạc với đoàn. */
    public function nguoiLienHe(): static
    {
        return $this->state(fn (): array => ['is_contact' => true]);
    }
}
