<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\DiscountCode;
use App\Models\PaymentLog;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DemoBookingSeeder extends Seeder
{
    private const DEMO_NOTE = '[demo] Đơn dữ liệu trình diễn';

    public function run(): void
    {
        $tours = Tour::query()
            ->with(['schedules', 'itineraries'])
            ->whereIn('slug', [
                'tour-ha-long-3n2d',
                'tour-da-nang-hoi-an-4n3d',
                'tour-sapa-fansipan-2n1d',
                'tour-phu-quoc-3n2d',
            ])
            ->get()
            ->keyBy('slug');

        $customers = User::query()
            ->where('role', 'customer')
            ->orderBy('id')
            ->take(4)
            ->get()
            ->values();

        if ($tours->isEmpty() || $customers->isEmpty()) {
            return;
        }

        $guide = User::query()->where('role', 'guide')->first();

        // Mã giảm giá khớp với banner "giảm 15% cho lần đặt tour đầu tiên" trên trang chủ
        DiscountCode::query()->updateOrCreate(
            ['code' => 'WELCOME15'],
            [
                'name' => 'Ưu đãi lần đặt tour đầu tiên',
                'type' => 'percent',
                'value' => 15,
                'minimum_order_amount' => 1000000,
                'max_discount_amount' => 1000000,
                'usage_limit' => 100,
                'starts_at' => now()->subDay(),
                'expires_at' => now()->addMonths(3),
                'is_active' => true,
            ]
        );

        /*
         * Xóa dữ liệu demo cũ để seed lại được nhiều lần.
         *
         * Xóa bút toán và nhật ký cổng TRƯỚC: chúng trỏ tới đơn bằng khóa ngoại, và từ lần seed
         * này trở đi mỗi đơn demo đều kéo theo vài dòng ở hai bảng ấy.
         */
        $donCu = Booking::query()->where('note', 'like', '[demo]%')->pluck('id');

        if ($donCu->isNotEmpty()) {
            BookingPayment::query()->whereIn('booking_id', $donCu)->delete();
            PaymentLog::query()->whereIn('booking_id', $donCu)->delete();
            Booking::query()->whereIn('id', $donCu)->delete();
        }

        // [slug, số tháng trước, trạng thái, người lớn, trẻ em, thanh toán online?]
        $entries = [
            ['tour-ha-long-3n2d', 5, 'confirmed', 2, 0, true],
            ['tour-ha-long-3n2d', 4, 'confirmed', 2, 1, true],
            ['tour-ha-long-3n2d', 3, 'confirmed', 1, 0, false],
            ['tour-ha-long-3n2d', 2, 'confirmed', 3, 1, true],
            ['tour-ha-long-3n2d', 1, 'confirmed', 2, 0, true],
            ['tour-ha-long-3n2d', 0, 'confirmed', 2, 1, true],
            ['tour-ha-long-3n2d', 0, 'pending', 1, 0, false],
            ['tour-ha-long-3n2d', 1, 'cancelled', 2, 0, false],
            ['tour-da-nang-hoi-an-4n3d', 4, 'confirmed', 2, 0, true],
            ['tour-da-nang-hoi-an-4n3d', 2, 'confirmed', 2, 2, true],
            ['tour-da-nang-hoi-an-4n3d', 0, 'confirmed', 1, 1, false],
            ['tour-da-nang-hoi-an-4n3d', 3, 'cancelled', 1, 0, false],
            ['tour-sapa-fansipan-2n1d', 1, 'confirmed', 2, 0, true],
            ['tour-sapa-fansipan-2n1d', 0, 'confirmed', 1, 0, true],
            ['tour-phu-quoc-3n2d', 2, 'confirmed', 2, 1, true],
            ['tour-phu-quoc-3n2d', 0, 'pending', 2, 0, false],
        ];

        foreach ($entries as $index => [$slug, $monthsAgo, $status, $adults, $children, $paidOnline]) {
            $tour = $tours->get($slug);
            $schedule = $tour?->schedules->first();

            if (!$tour || !$schedule) {
                continue;
            }

            $customer = $customers[$index % $customers->count()];
            $guests = $adults + $children;
            $amount = $adults * (float) $tour->adult_price + $children * (float) $tour->child_price;
            $createdAt = now()->subMonths($monthsAgo)->startOfMonth()->addDays(4 + ($index * 2) % 20)->setTime(9 + $index % 8, 15);

            $booking = Booking::create([
                'public_token' => (string) Str::uuid(),
                'tour_id' => $tour->id,
                'customer_id' => $customer->id,
                'tour_schedule_id' => $schedule->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => '09' . str_pad((string) (10000000 + $index * 137), 8, '0', STR_PAD_LEFT),
                'departure_date' => $schedule->start_date,
                'guests' => $guests,
                'adult_count' => $adults,
                'child_count' => $children,
                'infant_count' => 0,
                'total_amount' => $amount,
                'status' => $status,
                'expires_at' => $status === 'pending' ? now()->addDay() : null,
                'cancel_reason' => $status === 'cancelled' ? 'Khách bận việc đột xuất, không tham gia được' : null,
                'vnpay_transaction_no' => ($status === 'confirmed' && $paidOnline) ? 'VNP' . (14500000 + $index * 331) : null,
                'paid_at' => ($status === 'confirmed' && $paidOnline) ? $createdAt->copy()->addMinutes(6) : null,
                'confirmed_at' => $status === 'confirmed' ? $createdAt->copy()->addMinutes(6) : null,
                'note' => self::DEMO_NOTE,
            ]);

            $booking->created_at = $createdAt;
            $booking->updated_at = $createdAt->copy()->addMinutes(6);
            $booking->save();

            /*
             * Sổ giao dịch và nhật ký cổng thanh toán cho đơn đã trả tiền.
             *
             * Không có phần này thì hai màn tiền — sổ trong chi tiết đơn, và sổ tổng ở
             * `/admin/transactions` — mở ra trống trơn trên dữ liệu mẫu, dù cả hai đều chạy đúng.
             * Tính năng có mà không nhìn thấy được thì lúc trình bày cũng như không có.
             */
            if ($status === 'confirmed') {
                $this->ghiSoVaNhatKy($booking, $paidOnline, $index, $createdAt);
            }

            // Vài đơn có danh sách hành khách chi tiết
            if ($index % 3 === 0) {
                $booking->passengers()->create([
                    'name' => mb_strtoupper($customer->name),
                    'type' => 'adult',
                    'identity_number' => '0790950' . str_pad((string) (10000 + $index * 91), 5, '0', STR_PAD_LEFT),
                ]);

                if ($children > 0) {
                    $booking->passengers()->create([
                        'name' => mb_strtoupper($customer->name) . ' (BE)',
                        'type' => 'child',
                        'note' => 'Bé hay say xe',
                    ]);
                }
            }
        }

        // Đồng bộ lại số chỗ đã đặt theo đơn còn hiệu lực
        TourSchedule::query()->each(function (TourSchedule $schedule) {
            $schedule->update([
                'booked_people' => (int) Booking::query()
                    ->where('tour_schedule_id', $schedule->id)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->sum('guests'),
            ]);
        });

        $this->donChuaThuDu($tours, $customers);
        $this->donDaHuyConNoHoan($tours, $customers);

        // Điểm danh không seed ở đây nữa.
        //
        // Seeder này từng ghi vào booking_checkins, tức bảng cũ theo đơn và theo ngày. Từ nhóm H
        // thì điểm danh nằm ở passenger_checkins theo từng người tại từng điểm dừng, và migration
        // chuyển dữ liệu đã chạy xong từ trước lúc seed. Ghi tiếp vào bảng cũ chỉ tạo ra dữ liệu
        // không màn hình nào đọc, lại làm tưởng là đã có dữ liệu điểm danh.
        //
        // Dữ liệu điểm danh để thử tay nằm ở BusinessScenarioSeeder.
        unset($guide);
    }

    /**
     * Một bút toán thu, và nhật ký cổng thanh toán nếu tiền về qua VNPay.
     *
     * Cứ đơn thứ năm thì thêm một lượt cổng THẤT BẠI trước lượt thành công. Nhật ký chỉ toàn dòng
     * xanh không cho thấy nó dùng để làm gì; có một dòng hỏng bên cạnh thì mới thấy hệ thống ghi
     * lại cả những lượt không thành công, kèm mã lỗi cổng trả về.
     */
    private function ghiSoVaNhatKy(Booking $booking, bool $paidOnline, int $index, Carbon $createdAt): void
    {
        $luc = $createdAt->copy()->addMinutes(6);
        $soTien = (float) $booking->total_amount;

        if ($paidOnline && $index % 5 === 0) {
            PaymentLog::query()->create([
                'booking_id' => $booking->id,
                'provider' => 'vnpay',
                'transaction_no' => null,
                'bank_code' => 'NCB',
                // 24 = khách bấm hủy ở trang cổng. Lượt này không sinh bút toán nào.
                'response_code' => '24',
                'transaction_status' => '02',
                'amount' => $soTien,
                'is_valid_signature' => true,
                'raw_payload' => ['vnp_ResponseCode' => '24'],
                'created_at' => $luc->copy()->subMinutes(3),
                'updated_at' => $luc->copy()->subMinutes(3),
            ]);
        }

        BookingPayment::query()->create([
            'booking_id' => $booking->id,
            'kind' => 'balance',
            'amount' => $soTien,
            'method' => $paidOnline ? 'gateway' : 'bank_transfer',
            'reference' => $paidOnline
                ? $booking->vnpay_transaction_no
                : 'FT' . (26000000 + $index * 7919),
            'note' => $paidOnline ? 'Thanh toán qua VNPay' : 'Khách chuyển khoản, điều hành ghi nhận',
            'paid_at' => $luc,
            'created_at' => $luc,
            'updated_at' => $luc,
        ]);

        if ($paidOnline) {
            PaymentLog::query()->create([
                'booking_id' => $booking->id,
                'provider' => 'vnpay',
                'transaction_no' => $booking->vnpay_transaction_no,
                'bank_code' => ['NCB', 'VCB', 'TCB', 'MBB'][$index % 4],
                'response_code' => '00',
                'transaction_status' => '00',
                'amount' => $soTien,
                'is_valid_signature' => true,
                'raw_payload' => ['vnp_ResponseCode' => '00'],
                'created_at' => $luc,
                'updated_at' => $luc,
            ]);
        }
    }

    /**
     * Một đơn khách chuyển khoản THIẾU.
     *
     * Đây là tình huống duy nhất khiến một đơn đã xác nhận vẫn còn nợ tiền, và là lý do màn chi
     * tiết đơn có dòng "Còn thiếu" cùng liên kết trả nốt. Không có đơn nào như vậy trong dữ liệu
     * mẫu thì cả hai thứ đó không bao giờ hiện ra để nhìn.
     *
     * @param  \Illuminate\Support\Collection<string, Tour>  $tours
     * @param  \Illuminate\Support\Collection<int, User>  $customers
     */
    private function donChuaThuDu($tours, $customers): void
    {
        $tour = $tours->get('tour-da-nang-hoi-an-4n3d');
        $schedule = $tour?->schedules->first();

        if (!$tour || !$schedule) {
            return;
        }

        $khach = $customers->first();
        $tong = 2 * (float) $tour->adult_price;
        $luc = now()->subDays(9)->setTime(10, 30);

        $booking = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'customer_id' => $khach->id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => $khach->name,
            'customer_email' => $khach->email,
            'customer_phone' => '0912345678',
            'departure_date' => $schedule->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => $tong,
            'status' => 'confirmed',
            'confirmed_at' => $luc,
            'note' => self::DEMO_NOTE . ' — chuyển khoản thiếu',
        ]);

        BookingPayment::query()->create([
            'booking_id' => $booking->id,
            'kind' => 'balance',
            'amount' => round($tong * 0.6),
            'method' => 'bank_transfer',
            'reference' => 'FT26091234',
            'note' => 'Khách chuyển thiếu, hẹn trả nốt trước ngày đi',
            'paid_at' => $luc,
            'created_at' => $luc,
            'updated_at' => $luc,
        ]);
    }

    /**
     * Một đơn đã hủy còn nợ tiền hoàn, kèm tài khoản khách đã khai.
     *
     * Để màn `/admin/refunds` có việc để làm khi mở ra, thay vì báo "không còn khoản nào phải trả".
     *
     * @param  \Illuminate\Support\Collection<string, Tour>  $tours
     * @param  \Illuminate\Support\Collection<int, User>  $customers
     */
    private function donDaHuyConNoHoan($tours, $customers): void
    {
        $tour = $tours->get('tour-sapa-fansipan-2n1d');
        $schedule = $tour?->schedules->first();

        if (!$tour || !$schedule) {
            return;
        }

        $khach = $customers->last();
        $tong = 2 * (float) $tour->adult_price;
        $luc = now()->subDays(4)->setTime(15, 0);

        $booking = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'customer_id' => $khach->id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => $khach->name,
            'customer_email' => $khach->email,
            'customer_phone' => '0987654321',
            'departure_date' => $schedule->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => $tong,
            'status' => 'cancelled',
            'cancel_type' => 'by_customer',
            'cancel_reason' => 'Gia đình có việc đột xuất, xin hủy chuyến',
            'cancelled_at' => $luc,
            'seats_released' => true,
            'seats_released_at' => $luc,
            // Hoàn 90%: hủy sớm, theo bậc đầu của bảng phí.
            'refund_amount' => round($tong * 0.9),
            'refund_bank_account' => '0123456789',
            'refund_bank_name' => 'Vietcombank',
            'refund_account_holder' => mb_strtoupper($khach->name),
            'paid_at' => $luc->copy()->subDays(20),
            'note' => self::DEMO_NOTE . ' — đã hủy, chờ hoàn tiền',
        ]);

        BookingPayment::query()->create([
            'booking_id' => $booking->id,
            'kind' => 'balance',
            'amount' => $tong,
            'method' => 'gateway',
            'reference' => 'VNP14899001',
            'note' => 'Thanh toán qua VNPay',
            'paid_at' => $luc->copy()->subDays(20),
            'created_at' => $luc->copy()->subDays(20),
            'updated_at' => $luc->copy()->subDays(20),
        ]);
    }
}
