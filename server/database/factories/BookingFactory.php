<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 *
 * Đơn đặt tour dựng sẵn, dùng cho seeder kịch bản và cho kiểm thử.
 *
 * Hai điều factory này tự lo, vì để nơi gọi tự nhớ thì sớm muộn cũng có chỗ quên:
 *
 *   - `guests` luôn bằng tổng ba con số lứa tuổi (`soKhach()`), và `seats` bằng người lớn cộng trẻ
 *     em - em bé không chiếm ghế riêng. `seats` là con số hệ thống trừ vào kho chỗ của chuyến; để
 *     nó lệch với cơ cấu khách là dựng sẵn một bất biến đã vỡ.
 *   - Các mốc thời gian nằm đúng thứ tự: trả tiền sau khi đặt, không phải trước. Việc dọn này chạy
 *     ở `afterMaking` nên gọi `datLuc()` trước hay sau `daThanhToan()` đều ra cùng kết quả.
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            /*
             * `tour_schedule_id` khai TRƯỚC hai trường dưới có chủ ý.
             *
             * Laravel giải các thuộc tính theo đúng thứ tự khai báo, nên tới lượt hai closure bên
             * dưới thì chuyến đã là một id thật để tra ngược. Đảo thứ tự là chúng nhận về một đối
             * tượng factory chưa dựng.
             */
            'tour_schedule_id' => TourSchedule::factory(),
            'tour_id' => fn (array $daCo) => TourSchedule::query()
                ->whereKey($daCo['tour_schedule_id'])
                ->value('tour_id'),
            'departure_date' => fn (array $daCo) => TourSchedule::query()
                ->whereKey($daCo['tour_schedule_id'])
                ->value('start_date'),

            'public_token' => (string) Str::uuid(),
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->unique()->safeEmail(),
            'customer_phone' => '09' . $this->faker->numerify('########'),

            'guests' => 1,
            'seats' => 1,
            'adult_count' => 1,
            'child_count' => 0,
            'infant_count' => 0,

            // 0 nghĩa là "chưa ai đặt giá": `afterMaking` tính lại theo bảng giá của tour.
            'total_amount' => 0,

            'status' => BookingStatus::Pending->value,
            'expires_at' => now()->addMinutes((int) config('booking.payment_ttl_minutes', 10)),

            // Đơn chép chính sách hủy tại thời điểm đặt, đúng như luồng thật.
            'cancellation_policy_id' => CancellationPolicy::dangApDung()?->id,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Booking $don): void {
            $this->apGiaTheoTour($don);
            // Tính ở đây chứ không chỉ trong `soKhach()`: nơi gọi hoàn toàn có thể đặt thẳng
            // `adult_count` qua `create([...])`, và đường ấy cũng phải ra đúng số ghế.
            $don->seats = Booking::tinhSoGhe((int) $don->adult_count, (int) $don->child_count);
            $this->donDepMocThoiGian($don);
        });
    }

    /** Gắn đơn vào một chuyến cụ thể: tour, ngày khởi hành và bảng giá đều lấy theo chuyến đó. */
    public function choChuyen(TourSchedule $chuyen): static
    {
        return $this->state(fn (): array => [
            'tour_id' => $chuyen->tour_id,
            'tour_schedule_id' => $chuyen->getKey(),
            'departure_date' => $chuyen->start_date,
        ]);
    }

    /** Số khách theo từng lứa tuổi. `guests` và `seats` suy ra từ đây, không khai riêng. */
    public function soKhach(int $nguoiLon, int $treEm = 0, int $emBe = 0): static
    {
        return $this->state(fn (): array => [
            'adult_count' => $nguoiLon,
            'child_count' => $treEm,
            'infant_count' => $emBe,
            'guests' => $nguoiLon + $treEm + $emBe,
            'seats' => Booking::tinhSoGhe($nguoiLon, $treEm),
        ]);
    }

    /** Đã trả tiền và đã vào danh sách đoàn gửi nhà cung cấp. */
    public function daThanhToan(): static
    {
        return $this->state(fn (): array => [
            'status' => BookingStatus::Confirmed->value,
            'paid_at' => now(),
            'confirmed_at' => now(),
            'expires_at' => null,
        ]);
    }

    /**
     * Còn giữ chỗ, chưa trả tiền.
     *
     * `$giuThemNgay` cố ý dài hơn TTL thật nhiều lần: đơn dựng cho một buổi thử tay phải sống hết
     * buổi, không bị lệnh nhả chỗ quét mất giữa chừng rồi người thử tưởng mình bấm nhầm.
     */
    public function dangGiuCho(int $giuThemNgay = 3): static
    {
        return $this->state(fn (): array => [
            'status' => BookingStatus::Pending->value,
            'paid_at' => null,
            'confirmed_at' => null,
            'expires_at' => now()->addDays($giuThemNgay),
        ]);
    }

    /** Thời điểm khách bấm đặt. */
    public function datLuc(DateTimeInterface $luc): static
    {
        return $this->state(fn (): array => [
            'created_at' => Carbon::instance($luc),
            'updated_at' => Carbon::instance($luc),
        ]);
    }

    /** Đơn thuộc về một tài khoản có sẵn, để đăng nhập vào xem được ở mục "đơn của tôi". */
    public function cuaTaiKhoan(User $khach): static
    {
        return $this->state(fn (): array => [
            'customer_id' => $khach->getKey(),
            'customer_email' => $khach->email,
        ]);
    }

    /** Tổng tiền theo bảng giá của tour, trừ khi nơi gọi đã tự đặt một con số khác. */
    private function apGiaTheoTour(Booking $don): void
    {
        $tour = Tour::withTrashed()->find($don->tour_id);

        if (!$tour) {
            return;
        }

        /*
         * Chép đơn giá vào đơn như luồng đặt thật, kể cả khi nơi gọi đã tự đặt tổng tiền: chứng từ
         * đọc ba cột này chứ không đọc qua tour, nên đơn thiếu chúng sẽ in ra bảng giá rỗng.
         */
        $don->adult_price ??= $tour->adult_price;
        $don->child_price ??= $tour->child_price;
        $don->infant_price ??= $tour->infant_price;

        if ((float) $don->total_amount > 0) {
            return;
        }

        $don->total_amount = (int) $don->adult_count * (float) $tour->adult_price
            + (int) $don->child_count * (float) $tour->child_price
            + (int) $don->infant_count * (float) $tour->infant_price;
    }

    /**
     * Kéo các mốc của đơn về sau thời điểm đặt.
     *
     * `daThanhToan()` đặt `paid_at = now()`, còn `datLuc()` có thể lùi ngày đặt về vài tuần trước.
     * Không dọn lại thì đơn hiện ra là trả tiền trước khi đặt - vô lý trên màn hình, và làm hỏng
     * mọi phép đọc theo trục thời gian.
     */
    private function donDepMocThoiGian(Booking $don): void
    {
        $datLuc = $don->created_at;

        if (!$datLuc instanceof Carbon) {
            return;
        }

        $xong = $datLuc->copy()->addMinutes(8);

        if ($don->paid_at) {
            $don->paid_at = $xong;
        }

        if ($don->confirmed_at) {
            $don->confirmed_at = $xong;
        }

        $don->updated_at = $xong;
    }
}
