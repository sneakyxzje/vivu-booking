<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\PassengerCheckinStatus;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\CancellationPolicy;
use App\Models\ItineraryCheckpoint;
use App\Models\PassengerCheckin;
use App\Models\PassengerCheckinHistory;
use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Dựng sẵn mọi tình huống của nhóm A, B, C, D, H để thử tay trên giao diện.
 *
 * Khác SampleTourSeeder ở mục đích: seeder kia dựng cửa hàng cho đẹp, seeder này dựng phòng
 * thí nghiệm. Mọi chuyến đều nằm trong một tour duy nhất để mở màn quản trị là thấy hết sáu
 * trạng thái cạnh nhau, thay vì phải đi tìm từng chỗ.
 *
 * Mốc thời gian được tính lùi từ lúc chạy seeder, nên chạy lại lúc nào cũng ra đúng tình huống
 * đó. Đổi lại, dữ liệu cũ dần trôi khỏi mốc: seed lại trước mỗi buổi thử.
 *
 * Chạy riêng:  php artisan db:seed --class=BusinessScenarioSeeder
 *
 * Bảng tình huống in ra cuối lần chạy. Kịch bản thử tay chi tiết ở
 * docs/nghiep-vu/15-verify-a-den-h.md.
 */
class BusinessScenarioSeeder extends Seeder
{
    private const TOUR_SLUG = 'tour-thu-nghiem-nghiep-vu';

    /** Dấu để xóa sạch dữ liệu của lần seed trước, không đụng dữ liệu khác. */
    private const TAG = '[kich-ban]';

    private Tour $tour;
    private ?User $guide = null;
    private User $customer;
    private ?User $admin = null;
    private ?CancellationPolicy $policy = null;

    /** @var array<string, TourSchedule> */
    private array $schedules = [];

    /** @var array<int, array{ma: string, tinh_huong: string, nhom: string}> */
    private array $summary = [];

    public function run(): void
    {
        $this->admin = User::query()->where('role', 'admin')->first();
        $this->guide = User::query()->where('role', 'guide')->first();
        $this->policy = CancellationPolicy::query()->where('is_default', true)->first();

        if (!$this->admin) {
            $this->command?->warn('Chưa có tài khoản admin, bỏ qua BusinessScenarioSeeder.');

            return;
        }

        $this->customer = $this->resolveCustomer();

        $this->donDepLanTruoc();
        $this->dungTour();
        $this->dungCacChuyen();
        $this->dungCacDon();
        $this->dungDiemDanh();
        $this->dongBoSoCho();
        $this->inBangTinhHuong();
    }

    /**
     * Mọi đơn kịch bản gắn vào một tài khoản khách duy nhất, để đăng nhập một lần là xem được
     * hết. Thực tế thì mỗi đơn một người, nhưng ở đây ưu tiên thử cho nhanh.
     */
    private function resolveCustomer(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'customer@gmail.com'],
            [
                'name' => 'Customer User',
                'password' => bcrypt('customer123'),
                'role' => 'customer',
                'status' => 'active',
            ],
        );
    }

    private function donDepLanTruoc(): void
    {
        $tourCu = Tour::query()->where('slug', self::TOUR_SLUG)->first();

        if ($tourCu) {
            // Xóa đơn trước, vì khóa ngoại tour_schedule_id chặn xóa chuyến khi còn đơn.
            Booking::query()->where('tour_id', $tourCu->id)->delete();
            $tourCu->schedules()->delete();
            $tourCu->itineraries()->delete();
            $tourCu->delete();
        }

        Booking::query()->where('note', 'like', self::TAG . '%')->delete();
    }

    private function dungTour(): void
    {
        $this->tour = Tour::query()->create([
            'admin_id' => $this->admin->id,
            'cancellation_policy_id' => $this->policy?->id,
            'title' => 'Tour Thử Nghiệm Nghiệp Vụ 3N2Đ',
            'slug' => self::TOUR_SLUG,
            'description' => 'Tour dựng riêng để thử tay toàn bộ tình huống của nhóm A, B, C, D, H. '
                . 'Không phải sản phẩm bán thật.',
            'price' => 5000000,
            'adult_price' => 5000000,
            'child_price' => 3500000,
            'infant_price' => 0,
            'thumbnail' => 'https://images.unsplash.com/photo-1528127269322-539801943592',
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'start_location' => 'Hà Nội',
            'end_location' => 'Hạ Long',
            'vehicle_info' => 'Xe 29 chỗ',
            'pickup_location' => 'Nhà hát Lớn Hà Nội',
            'is_featured' => false,
            'status' => 'active',
        ]);

        // Ba ngày, mỗi ngày hai điểm dừng. Điểm dừng phải có tọa độ, nếu không thì luồng tải
        // ảnh check-in từ chối ngay vì không có gì để đối chiếu khoảng cách.
        $lichTrinh = [
            [
                'day_number' => 1,
                'title' => 'Hà Nội - Hạ Long',
                'content' => 'Khởi hành sáng sớm, dừng nghỉ giữa đường, nhận phòng buổi chiều.',
                'checkpoints' => [
                    ['name' => 'Điểm đón Nhà hát Lớn', 'sequence' => 1, 'is_required_photo' => false, 'lat' => 21.0245, 'lng' => 105.8572],
                    ['name' => 'Trạm dừng nghỉ Hải Dương', 'sequence' => 2, 'is_required_photo' => true, 'lat' => 20.9373, 'lng' => 106.3145],
                ],
            ],
            [
                'day_number' => 2,
                'title' => 'Du thuyền vịnh Hạ Long',
                'content' => 'Lên tàu tham quan vịnh, ghé hang động, ăn trưa trên tàu.',
                'checkpoints' => [
                    ['name' => 'Bến tàu Tuần Châu', 'sequence' => 1, 'is_required_photo' => true, 'lat' => 20.9250, 'lng' => 106.9877],
                    ['name' => 'Hang Sửng Sốt', 'sequence' => 2, 'is_required_photo' => false, 'lat' => 20.8480, 'lng' => 107.0980],
                ],
            ],
            [
                'day_number' => 3,
                'title' => 'Hạ Long - Hà Nội',
                'content' => 'Ăn sáng, trả phòng, về Hà Nội buổi chiều.',
                'checkpoints' => [
                    ['name' => 'Sảnh khách sạn, trả phòng', 'sequence' => 1, 'is_required_photo' => false, 'lat' => 20.9510, 'lng' => 107.0700],
                    ['name' => 'Điểm trả khách Nhà hát Lớn', 'sequence' => 2, 'is_required_photo' => false, 'lat' => 21.0245, 'lng' => 105.8572],
                ],
            ],
        ];

        foreach ($lichTrinh as $ngay) {
            $itinerary = $this->tour->itineraries()->create([
                'day_number' => $ngay['day_number'],
                'title' => $ngay['title'],
                'content' => $ngay['content'],
            ]);

            foreach ($ngay['checkpoints'] as $diem) {
                $itinerary->checkpoints()->create([
                    'name' => $diem['name'],
                    'sequence' => $diem['sequence'],
                    'is_required_photo' => $diem['is_required_photo'],
                    'latitude' => $diem['lat'],
                    'longitude' => $diem['lng'],
                ]);
            }
        }
    }

    /**
     * Chín chuyến phủ hết sáu trạng thái vòng đời và năm mốc phí hủy.
     *
     * Số giờ tới lúc khởi hành quyết định mức hoàn, hạn chốt mặc định là 72 giờ trước khởi hành
     * quyết định chỗ có trả về kho hay không. Hai cái cổng đó đặt ở hai mốc khác nhau, nên có
     * chuyến rơi vào cùng bậc phí mà khác nhau về chỗ - đó là S3 với S4 bên dưới.
     */
    private function dungCacChuyen(): void
    {
        $chuyen = [
            ['ma' => 'S1', 'gio' => 480, 'status' => ScheduleStatus::Open, 'min' => 4, 'mo_ta' => 'Hoàn 90%, còn xa hạn chốt'],
            ['ma' => 'S2', 'gio' => 240, 'status' => ScheduleStatus::Open, 'min' => 4, 'mo_ta' => 'Hoàn 70%'],
            ['ma' => 'S3', 'gio' => 120, 'status' => ScheduleStatus::Open, 'min' => 4, 'mo_ta' => 'Hoàn 50%, còn 48 giờ nữa mới tới hạn chốt'],
            ['ma' => 'S4', 'gio' => 60, 'status' => ScheduleStatus::Closed, 'min' => 4, 'mo_ta' => 'Hoàn 30%, ĐÃ QUA hạn chốt nên hủy sinh ghế chết'],
            ['ma' => 'S5', 'gio' => 26, 'status' => ScheduleStatus::Confirmed, 'min' => 4, 'mo_ta' => 'Hoàn 0%, đã chốt danh sách'],
            ['ma' => 'S6', 'gio' => -24, 'status' => ScheduleStatus::InProgress, 'min' => 4, 'mo_ta' => 'Đang chạy: khóa hủy, mở điểm danh'],
            ['ma' => 'S7', 'gio' => -120, 'status' => ScheduleStatus::Completed, 'min' => 4, 'mo_ta' => 'Đã kết thúc, đơn chờ chốt'],
            ['ma' => 'S8', 'gio' => 360, 'status' => ScheduleStatus::Cancelled, 'min' => 4, 'mo_ta' => 'Chuyến bị hủy, trạng thái cuối'],
            ['ma' => 'S9', 'gio' => 720, 'status' => ScheduleStatus::Open, 'min' => 6, 'mo_ta' => 'Thiếu khách so với mức tối thiểu'],
        ];

        foreach ($chuyen as $item) {
            $start = now()->addHours($item['gio'])->startOfHour();
            $end = $start->copy()->addDays(2)->endOfDay();

            $payload = [
                'tour_id' => $this->tour->id,
                'guide_id' => $this->guide?->id,
                'start_date' => $start,
                'end_date' => $end,
                'booking_deadline' => $start->copy()->subDays(
                    (int) config('booking.booking_deadline_days', 3)
                ),
                'max_people' => 10,
                'min_people' => $item['min'],
                'booked_people' => 0,
                'status' => $item['status']->value,
            ];

            if ($item['status'] === ScheduleStatus::Confirmed) {
                $payload['confirmed_at'] = now()->subHours(6);
            }

            if ($item['status'] === ScheduleStatus::Cancelled) {
                $payload['cancelled_at'] = now()->subDay();
                $payload['cancelled_by'] = $this->admin->id;
                $payload['cancelled_reason'] = 'Không đủ khách tối thiểu, đã báo và hoàn tiền cho khách.';
            }

            $this->schedules[$item['ma']] = TourSchedule::query()->create($payload);
        }
    }

    private function dungCacDon(): void
    {
        // Nhóm B: mỗi chuyến một đơn đã thanh toán, để bấm hủy và xem báo giá hoàn.
        $this->taoDon('S1', 'confirmed', 2, 0, 'Hủy thử: phải thấy hoàn 90%, chỗ trả về kho', 'B, C');
        $this->taoDon('S2', 'confirmed', 2, 0, 'Hủy thử: phải thấy hoàn 70%', 'B');
        $this->taoDon('S3', 'confirmed', 2, 1, 'Hủy thử: phải thấy hoàn 50%, chỗ vẫn trả về kho', 'B, C');
        $this->taoDon('S4', 'confirmed', 2, 0, 'Hủy thử: hoàn 30% nhưng chỗ KHÔNG trả về, sinh ghế chết', 'B, C');
        $this->taoDon('S5', 'confirmed', 2, 0, 'Hủy thử: hoàn 0%, chỗ không trả về', 'B, C');

        // Nhóm C: ghế chết có sẵn, để màn Chỗ đã hủy chưa mở bán lại có dữ liệu ngay.
        $gheChet = $this->taoDon('S4', 'cancelled', 3, 0, 'Ghế chết dựng sẵn: mở màn Chỗ đã hủy chưa mở bán lại', 'C');
        $gheChet->forceFill([
            'cancelled_at' => now()->subHours(5),
            'cancel_type' => 'customer',
            'cancelled_by' => $this->customer->id,
            'cancel_reason' => 'Khách có việc đột xuất, hủy sau khi đã chốt danh sách.',
            'seats_released' => false,
            'refund_amount' => 4500000,
        ])->save();

        // Đối chứng cho ghế chết: đơn chưa thanh toán thì luôn trả chỗ, kể cả quá hạn chốt.
        $quaHan = $this->taoDon('S5', 'pending', 2, 0, 'Chạy bookings:release-expired: đơn tự hủy và TRẢ chỗ dù đã qua hạn chốt', 'C');
        $quaHan->forceFill([
            'expires_at' => now()->subHours(2),
            'paid_at' => null,
            'confirmed_at' => null,
        ])->save();

        // X07: đơn vừa hủy trong 24 giờ, nút mở lại còn hiệu lực.
        $moiHuy = $this->taoDon('S1', 'cancelled', 1, 0, 'Mở lại đơn hủy nhầm: mới hủy 2 giờ trước nên còn trong hạn 24 giờ', 'X07');
        $moiHuy->forceFill([
            'cancelled_at' => now()->subHours(2),
            'cancel_type' => 'customer',
            'cancelled_by' => $this->customer->id,
            'cancel_reason' => 'Khách bấm nhầm, gọi điện xin mở lại.',
            'seats_released' => true,
            'seats_released_at' => now()->subHours(2),
        ])->save();

        // Nhóm D: đơn của chuyến đang chạy, thử hủy từ cả trang khách lẫn trang quản trị.
        $this->taoDon('S6', 'confirmed', 2, 0, 'Thử hủy: phải bị chặn ở CẢ trang khách lẫn trang quản trị', 'D', ['Nguyễn Văn An', 'Trần Thị Bình']);
        $this->taoDon('S6', 'confirmed', 2, 1, 'Điểm danh: đơn ba người, dùng để thử năm trạng thái', 'H', ['Lê Minh Cường', 'Phạm Thu Dung', 'Lê Bảo Duy']);
        $this->taoDon('S6', 'confirmed', 1, 0, 'Điểm danh: chưa ghi gì, để thử ghi lần đầu', 'H', ['Hoàng Văn Em']);

        // D03: chuyến đã xong, ba đơn ở ba tình trạng bằng chứng khác nhau.
        $this->taoDon('S7', 'confirmed', 2, 0, 'Chạy bookings:finalize-completed: có mặt đủ, thành ĐÃ HOÀN THÀNH', 'D03', ['Đỗ Quang Phúc', 'Đỗ Thị Giang']);
        $this->taoDon('S7', 'confirmed', 2, 0, 'Chạy bookings:finalize-completed: cả hai vắng ở điểm đón, thành KHÁCH KHÔNG CÓ MẶT', 'D03', ['Vũ Đình Hải', 'Vũ Thị Hoa']);
        $this->taoDon('S7', 'confirmed', 1, 0, 'Chạy bookings:finalize-completed: không điểm danh gì, vẫn thành ĐÃ HOÀN THÀNH vì thiếu bằng chứng', 'D03', ['Bùi Thanh Khang']);

        // Nhóm A: chuyến thiếu khách so với mức tối thiểu.
        $this->taoDon('S9', 'confirmed', 2, 0, 'Chạy schedules:confirm-ready: chuyến này thiếu khách nên bị cảnh báo, không chốt', 'A');
    }

    /**
     * @param  array<int, string>  $tenHanhKhach
     */
    private function taoDon(
        string $maChuyen,
        string $status,
        int $nguoiLon,
        int $treEm,
        string $tinhHuong,
        string $nhom,
        array $tenHanhKhach = [],
    ): Booking {
        $schedule = $this->schedules[$maChuyen];

        $tongTien = $nguoiLon * (float) $this->tour->adult_price
            + $treEm * (float) $this->tour->child_price;

        $daThanhToan = in_array($status, ['confirmed', 'cancelled'], true);

        $booking = Booking::query()->create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'customer_id' => $this->customer->id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => $this->customer->name,
            'customer_email' => $this->customer->email,
            'customer_phone' => '0901234567',
            'departure_date' => $schedule->start_date,
            'guests' => $nguoiLon + $treEm,
            'adult_count' => $nguoiLon,
            'child_count' => $treEm,
            'infant_count' => 0,
            'total_amount' => $tongTien,
            'status' => $status,
            // Sao chép chính sách vào đơn lúc đặt. Đây là điểm để thử "sửa chính sách không
            // hồi tố": đổi bảng phí ở màn quản trị rồi quay lại đơn này, số phải giữ nguyên.
            'cancellation_policy_id' => $this->policy?->id,
            'paid_at' => $daThanhToan ? now()->subDays(2) : null,
            'confirmed_at' => $daThanhToan ? now()->subDays(2) : null,
            'expires_at' => $status === 'pending' ? now()->addDay() : null,
            'vnpay_transaction_no' => $daThanhToan ? 'VNP' . random_int(10000000, 99999999) : null,
            'note' => self::TAG . ' ' . $tinhHuong,
        ]);

        foreach ($tenHanhKhach as $index => $ten) {
            BookingPassenger::query()->create([
                'booking_id' => $booking->id,
                'name' => $ten,
                'type' => $index < $nguoiLon ? 'adult' : 'child',
                'identity_number' => $index < $nguoiLon
                    ? '0' . random_int(10000000000, 99999999999)
                    : null,
            ]);
        }

        $this->summary[] = [
            'ma' => $maChuyen,
            'nhom' => $nhom,
            'tinh_huong' => $tinhHuong,
        ];

        return $booking;
    }

    /**
     * Điểm danh dựng sẵn cho chuyến đang chạy và chuyến đã kết thúc.
     *
     * Chuyến đang chạy để trên màn hướng dẫn viên có sẵn dữ liệu mà nhìn; chuyến đã kết thúc để
     * lệnh chốt đơn có ba tình trạng bằng chứng khác nhau mà xử lý.
     */
    private function dungDiemDanh(): void
    {
        $diemDon = $this->checkpointDauTien();

        if (!$diemDon || !$this->guide) {
            return;
        }

        // --- Chuyến đang chạy: ghi một phần, cố ý để dở --------------------------------
        $donDangChay = $this->donCuaChuyen('S6');

        if ($donDangChay->count() >= 2) {
            $donMotNguoi = $donDangChay->firstWhere('guests', 1);
            $donBaNguoi = $donDangChay->firstWhere('guests', 3);

            if ($donBaNguoi) {
                $hanhKhach = $donBaNguoi->passengers;

                $this->ghiDiemDanh($hanhKhach[0], $diemDon, PassengerCheckinStatus::Present);
                $this->ghiDiemDanh($hanhKhach[1], $diemDon, PassengerCheckinStatus::Late, 'Khách tới muộn 20 phút, xe đã chờ tại điểm đón.');

                // Bản ghi có lịch sử sửa, để màn báo cáo quản trị hiện được vết thay đổi.
                $suaLai = $this->ghiDiemDanh($hanhKhach[2], $diemDon, PassengerCheckinStatus::Absent, 'Gọi ba lần không nghe máy, xe phải rời điểm đón.');

                PassengerCheckinHistory::query()->create([
                    'passenger_checkin_id' => $suaLai->id,
                    'old_status' => PassengerCheckinStatus::Present->value,
                    'new_status' => PassengerCheckinStatus::Absent->value,
                    'note' => 'Ghi nhầm lúc đầu là có mặt, kiểm lại thì khách không lên xe.',
                    'changed_by' => $this->guide->id,
                    'changed_at' => now()->subHours(20),
                ]);
            }

            // Đơn một người cố ý để trống, để thử ghi lần đầu trên giao diện.
            unset($donMotNguoi);
        }

        // --- Chuyến đã kết thúc: ba tình trạng bằng chứng ------------------------------
        foreach ($this->donCuaChuyen('S7') as $don) {
            if (str_contains((string) $don->note, 'KHÔNG CÓ MẶT')) {
                foreach ($don->passengers as $hanhKhach) {
                    $this->ghiDiemDanh($hanhKhach, $diemDon, PassengerCheckinStatus::Absent, 'Khách không tới điểm đón, không liên lạc được.', 5);
                }

                continue;
            }

            if (str_contains((string) $don->note, 'thiếu bằng chứng')) {
                continue; // Cố ý không ghi gì.
            }

            foreach ($don->passengers as $hanhKhach) {
                $this->ghiDiemDanh($hanhKhach, $diemDon, PassengerCheckinStatus::Present, null, 5);
            }
        }
    }

    private function ghiDiemDanh(
        BookingPassenger $passenger,
        ItineraryCheckpoint $checkpoint,
        PassengerCheckinStatus $status,
        ?string $note = null,
        int $soNgayTruoc = 1,
    ): PassengerCheckin {
        return PassengerCheckin::query()->create([
            'booking_passenger_id' => $passenger->id,
            'tour_schedule_id' => $passenger->booking->tour_schedule_id,
            'itinerary_checkpoint_id' => $checkpoint->id,
            'status' => $status,
            'note' => $note,
            'checked_by' => $this->guide?->id,
            'checked_at' => now()->subDays($soNgayTruoc),
            'is_late_entry' => false,
        ]);
    }

    private function checkpointDauTien(): ?ItineraryCheckpoint
    {
        $ngayMot = TourItinerary::query()
            ->where('tour_id', $this->tour->id)
            ->where('day_number', 1)
            ->first();

        return $ngayMot?->checkpoints()->orderBy('sequence')->first();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Booking> */
    private function donCuaChuyen(string $maChuyen)
    {
        return Booking::query()
            ->where('tour_schedule_id', $this->schedules[$maChuyen]->id)
            ->where('status', BookingStatus::Confirmed->value)
            ->with('passengers')
            ->orderBy('id')
            ->get();
    }

    /**
     * Ghi lại số chỗ đã bán cho khớp thực tế.
     *
     * Dùng đúng công thức của lệnh bookings:check-seat-consistency: đơn còn hiệu lực, cộng đơn
     * đã hủy mà chỗ chưa trả về kho. Seed lệch số thì lệnh đối chiếu sẽ báo đỏ ngay lần chạy
     * đầu và không còn phân biệt được đâu là lỗi thật.
     */
    private function dongBoSoCho(): void
    {
        foreach ($this->schedules as $schedule) {
            $soCho = (int) Booking::query()
                ->where('tour_schedule_id', $schedule->id)
                ->where(function ($query) {
                    $query->where('status', '!=', 'cancelled')
                        ->orWhere(function ($gheChet) {
                            $gheChet->where('status', 'cancelled')
                                ->where('seats_released', false);
                        });
                })
                ->sum('guests');

            $schedule->forceFill(['booked_people' => $soCho])->save();
        }
    }

    private function inBangTinhHuong(): void
    {
        $this->command?->newLine();
        $this->command?->info('Đã dựng tour "' . $this->tour->title . '" (slug: ' . self::TOUR_SLUG . ')');
        $this->command?->newLine();

        $rows = [];

        foreach ($this->schedules as $ma => $schedule) {
            $gio = (int) round(now()->diffInHours($schedule->start_date, false));

            $rows[] = [
                $ma,
                $schedule->start_date->format('d/m H:i'),
                $gio >= 0 ? "còn {$gio}h" : 'đã qua ' . abs($gio) . 'h',
                ScheduleStatus::from($schedule->getRawOriginal('status'))->label(),
                (int) $schedule->booked_people . '/' . (int) $schedule->max_people,
            ];
        }

        $this->command?->table(
            ['Mã', 'Khởi hành', 'Cách hiện tại', 'Trạng thái', 'Chỗ'],
            $rows,
        );

        // Báo giá hoàn tiền hiện ở trang /booking-success/{public_token}, tra theo mã tra cứu
        // chứ không theo id đơn. In sẵn ở đây để mở thẳng bằng trình duyệt, khỏi phải đi tìm.
        $donHuyThu = Booking::query()
            ->where('tour_id', $this->tour->id)
            ->where('note', 'like', '%Hủy thử%')
            ->orderBy('id')
            ->get(['public_token', 'note', 'tour_schedule_id']);

        if ($donHuyThu->isNotEmpty()) {
            $this->command?->line('Xem mức hoàn dự kiến: mở /booking-success/<mã tra cứu>');

            $this->command?->table(
                ['Mức hoàn', 'Mã tra cứu'],
                $donHuyThu->map(function (Booking $don) {
                    preg_match('/hoàn (\d+%)/u', (string) $don->note, $khop);

                    return [$khop[1] ?? '?', $don->public_token];
                })->all(),
            );
        }

        $this->command?->line('Đăng nhập khách: customer@gmail.com / customer123');
        $this->command?->line('Kịch bản thử tay từng nhóm: docs/nghiep-vu/15-verify-a-den-h.md');
        $this->command?->newLine();
    }
}
