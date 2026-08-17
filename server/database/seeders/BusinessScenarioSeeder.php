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
use App\Services\BookingChangeRequestService;
use App\Services\ScheduleGuideService;
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

    /**
     * Cả đội hướng dẫn viên, sắp theo id.
     *
     * Cần nhiều người chứ không một: có nhiều người mới thấy được chuyến hai HDV, và mới thử
     * được luật chặn trùng lịch - muốn chặn thì phải có người thứ hai để so.
     *
     * @var \Illuminate\Support\Collection<int, User>
     */
    private $guides;

    private ?User $guide = null;
    private User $customer;
    private ?User $admin = null;
    private ?CancellationPolicy $policy = null;

    /** @var array<string, TourSchedule> */
    private array $schedules = [];

    /**
     * Các đơn cần gọi tên đích danh trong bản hướng dẫn in ra cuối lần chạy.
     *
     * Người thử tay nhìn màn hình chỉ thấy "đơn #37", không thấy nhãn kịch bản nào. Bảng hướng
     * dẫn phải nói bằng đúng con số họ nhìn thấy, nếu không mỗi bước lại phải tự dò xem đơn nào
     * là đơn nào.
     *
     * @var array<string, Booking>
     */
    private array $don = [];

    public function run(): void
    {
        $this->admin = User::query()->where('role', 'admin')->first();
        $this->guides = User::query()->where('role', 'guide')->where('status', 'active')->orderBy('id')->get();
        $this->guide = $this->guides->first();
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
        $this->dungYeuCauHuy();
        $this->dungDiemDanh();
        $this->dongBoSoCho();
        $this->inHuongDan();
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
            // so_hdv: số hướng dẫn viên muốn phân công. Không có luật nào ràng buộc con số này
            // với số khách - đặt khác nhau ở đây chỉ để lúc thử tay nhìn thấy đủ ba trạng thái:
            // chuyến nhiều người dẫn, chuyến một người, và chuyến chưa phân công ai.
            ['ma' => 'S1', 'gio' => 480, 'status' => ScheduleStatus::Open, 'min' => 4, 'so_hdv' => 3, 'mo_ta' => 'Hoàn 90%, còn xa hạn chốt'],
            ['ma' => 'S2', 'gio' => 240, 'status' => ScheduleStatus::Open, 'min' => 4, 'so_hdv' => 1, 'mo_ta' => 'Hoàn 70%'],
            ['ma' => 'S3', 'gio' => 120, 'status' => ScheduleStatus::Open, 'min' => 4, 'so_hdv' => 1, 'mo_ta' => 'Hoàn 50%, còn 48 giờ nữa mới tới hạn chốt'],
            ['ma' => 'S4', 'gio' => 60, 'status' => ScheduleStatus::Closed, 'min' => 4, 'so_hdv' => 1, 'mo_ta' => 'Hoàn 30%, ĐÃ QUA hạn chốt nên hủy sinh ghế chết'],
            ['ma' => 'S5', 'gio' => 26, 'status' => ScheduleStatus::Confirmed, 'min' => 4, 'so_hdv' => 1, 'mo_ta' => 'Hoàn 0%, đã chốt danh sách'],
            ['ma' => 'S6', 'gio' => -24, 'status' => ScheduleStatus::InProgress, 'min' => 4, 'so_hdv' => 2, 'mo_ta' => 'Đang chạy: khóa hủy, mở điểm danh'],
            ['ma' => 'S7', 'gio' => -120, 'status' => ScheduleStatus::Completed, 'min' => 4, 'so_hdv' => 1, 'mo_ta' => 'Đã kết thúc, đơn chờ chốt'],
            // Chuyến đã hủy thì không cần ai dẫn: để trống cho thấy trạng thái "Chưa phân công".
            ['ma' => 'S8', 'gio' => 360, 'status' => ScheduleStatus::Cancelled, 'min' => 4, 'so_hdv' => 0, 'mo_ta' => 'Chuyến bị hủy, trạng thái cuối'],
            // Hạn chốt rơi vào trong 18 giờ tới, tức nằm trong cửa sổ 24 giờ mà lệnh chốt chuyến
            // xét tới. Đặt xa hơn thì lệnh không nhìn tới chuyến này và chạy xong không thấy gì.
            ['ma' => 'S9', 'gio' => 90, 'status' => ScheduleStatus::Open, 'min' => 2, 'so_hdv' => 1, 'mo_ta' => 'Đủ khách tối thiểu và tới hạn chốt: lệnh sẽ tự chốt'],
            // Hai chuyến sát ngày nhau, mỗi chuyến ít khách. Đây là tình huống ghép chuyến:
            // không chuyến nào đủ mức tối thiểu, dồn về một thì cả hai đoàn đều được đi.
            ['ma' => 'S10', 'gio' => 600, 'status' => ScheduleStatus::Open, 'min' => 6, 'so_hdv' => 1, 'mo_ta' => 'Ít khách, dùng làm chuyến nguồn để ghép'],
            ['ma' => 'S11', 'gio' => 624, 'status' => ScheduleStatus::Open, 'min' => 6, 'so_hdv' => 2, 'mo_ta' => 'Ít khách, cách S10 một ngày, dùng làm chuyến đích'],
        ];

        foreach ($chuyen as $item) {
            $start = now()->addHours($item['gio'])->startOfHour();
            $end = $start->copy()->addDays(2)->endOfDay();

            $payload = [
                'tour_id' => $this->tour->id,
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

            $this->phanCongHuongDanVien($this->schedules[$item['ma']], (int) $item['so_hdv']);
        }
    }

    /**
     * Chọn đủ số hướng dẫn viên đang rảnh cho một chuyến.
     *
     * Chọn theo lịch trống thật chứ không gán cứng, vì các chuyến kịch bản nằm sát nhau và một
     * người không đứng ở hai đoàn cùng lúc. Trước đây seeder gán cùng một người cho cả mười một
     * chuyến, tức dữ liệu mẫu vi phạm chính cái luật mà hệ thống đang chặn ở giao diện.
     *
     * Dùng lại ScheduleGuideService để phép so trùng lịch ở đây giống hệt lúc chạy thật, thay vì
     * chép lại thành đoạn mã thứ hai rồi lệch dần.
     */
    private function phanCongHuongDanVien(TourSchedule $schedule, int $soNguoi): void
    {
        if ($soNguoi < 1 || $this->guides->isEmpty()) {
            return;
        }

        $service = app(ScheduleGuideService::class);
        [$start, $end] = $service->periodOf($schedule->setRelation('tour', $this->tour));

        $chon = [];

        foreach ($this->guides as $nguoi) {
            if (count($chon) >= $soNguoi) {
                break;
            }

            if ($service->conflictFor($nguoi->id, $start, $end, $schedule->getKey())) {
                continue;
            }

            $chon[] = $nguoi->id;
        }

        $schedule->guides()->sync($chon);
    }

    private function dungCacDon(): void
    {
        // Nhóm B: mỗi chuyến một đơn đã thanh toán, để bấm hủy và xem báo giá hoàn.
        $this->don['hoan90'] = $this->taoDon('S1', 'confirmed', 2, 0, 'Hủy thử: phải thấy hoàn 90%, chỗ trả về kho', 'B, C');
        $this->don['hoan70'] = $this->taoDon('S2', 'confirmed', 2, 0, 'Hủy thử: phải thấy hoàn 70%', 'B');
        $this->don['hoan50'] = $this->taoDon('S3', 'confirmed', 2, 1, 'Hủy thử: phải thấy hoàn 50%, chỗ vẫn trả về kho', 'B, C');
        $this->don['hoan30'] = $this->taoDon('S4', 'confirmed', 2, 0, 'Hủy thử: hoàn 30% nhưng chỗ KHÔNG trả về, sinh ghế chết', 'B, C');
        $this->don['hoan0'] = $this->taoDon('S5', 'confirmed', 2, 0, 'Hủy thử: hoàn 0%, chỗ không trả về', 'B, C');

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

        // Đơn chờ thanh toán còn hạn, để bấm thử nút hủy trên trang khách. Phải còn hạn: đơn quá
        // hạn bị tác vụ nhả chỗ hủy ngay lúc mở danh sách, chưa kịp bấm đã thành đã hủy.
        $khachTuHuy = $this->taoDon('S1', 'pending', 2, 0, 'Trang khách: bấm Hủy đơn để tự hủy đơn chưa thanh toán', 'D');
        $khachTuHuy->forceFill(['expires_at' => now()->addDay()])->save();
        $this->don['khachTuHuy'] = $khachTuHuy;

        // Đối chứng cho ghế chết: đơn chưa thanh toán thì luôn trả chỗ, kể cả quá hạn chốt.
        $quaHan = $this->taoDon('S5', 'pending', 2, 0, 'Chạy bookings:release-expired: đơn tự hủy và TRẢ chỗ dù đã qua hạn chốt', 'C');
        $quaHan->forceFill([
            'expires_at' => now()->subHours(2),
            'paid_at' => null,
            'confirmed_at' => null,
        ])->save();
        $this->don['quaHan'] = $quaHan;

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
        $this->don['moiHuy'] = $moiHuy;

        // Nhóm F: đơn đã thanh toán để khách gửi yêu cầu hủy, và một yêu cầu dựng sẵn để màn
        // duyệt của điều hành có dữ liệu ngay mà không phải tự gửi trước.
        $this->don['xinHuy'] = $this->taoDon('S2', 'confirmed', 2, 0, 'Trang khách: bấm Yêu cầu hủy, đơn đã thanh toán nên phải chờ duyệt', 'F');
        $this->don['choDuyet'] = $this->taoDon('S1', 'confirmed', 2, 0, 'Đã có yêu cầu hủy chờ duyệt, vào màn Yêu cầu hủy của khách để duyệt', 'F');

        // Nhóm D: đơn của chuyến đang chạy, thử hủy từ cả trang khách lẫn trang quản trị.
        $this->don['chuyenDangChay'] = $this->taoDon('S6', 'confirmed', 2, 0, 'Thử hủy: phải bị chặn vì chuyến đang chạy', 'D', ['Nguyễn Văn An', 'Trần Thị Bình']);
        $this->taoDon('S6', 'confirmed', 2, 1, 'Điểm danh: đơn ba người, dùng để thử năm trạng thái', 'H', ['Lê Minh Cường', 'Phạm Thu Dung', 'Lê Bảo Duy']);
        $this->taoDon('S6', 'confirmed', 1, 0, 'Điểm danh: chưa ghi gì, để thử ghi lần đầu', 'H', ['Hoàng Văn Em']);

        // D03: chuyến đã xong, ba đơn ở ba tình trạng bằng chứng khác nhau.
        $this->taoDon('S7', 'confirmed', 2, 0, 'Chạy bookings:finalize-completed: có mặt đủ, thành ĐÃ HOÀN THÀNH', 'D03', ['Đỗ Quang Phúc', 'Đỗ Thị Giang']);
        $this->taoDon('S7', 'confirmed', 2, 0, 'Chạy bookings:finalize-completed: cả hai vắng ở điểm đón, thành KHÁCH KHÔNG CÓ MẶT', 'D03', ['Vũ Đình Hải', 'Vũ Thị Hoa']);
        $this->taoDon('S7', 'confirmed', 1, 0, 'Chạy bookings:finalize-completed: không điểm danh gì, vẫn thành ĐÃ HOÀN THÀNH vì thiếu bằng chứng', 'D03', ['Bùi Thanh Khang']);

        // Nhóm A: hai mặt của lệnh chốt chuyến. S4 thiếu khách nên chỉ bị cảnh báo, S9 đủ khách
        // nên được chốt thật. Có cả hai mới thấy lệnh biết phân biệt, chứ không phải cứ tới hạn
        // là chốt bừa.
        $this->taoDon('S9', 'confirmed', 2, 0, 'Chạy schedules:confirm-ready: đủ khách nên chuyến được chốt tự động', 'A');

        // Nhóm L: hai chuyến sát ngày, mỗi chuyến ít khách. Chuyến nguồn có thêm một đơn chưa
        // thanh toán, để thấy nó bị hủy thay vì bị chuyển theo.
        $this->taoDon('S10', 'confirmed', 2, 0, 'Ghép chuyến: đơn đã thanh toán sẽ được chuyển sang chuyến đích', 'L');
        $donChuaTra = $this->taoDon('S10', 'pending', 2, 0, 'Ghép chuyến: đơn chưa thanh toán sẽ bị hủy và mời đặt lại', 'L');
        $donChuaTra->forceFill(['expires_at' => now()->addDay()])->save();
        $this->taoDon('S11', 'confirmed', 2, 0, 'Ghép chuyến: đơn có sẵn ở chuyến đích', 'L');
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

        unset($nhom);

        return $booking;
    }

    /**
     * Một yêu cầu hủy dựng sẵn ở trạng thái chờ duyệt.
     *
     * Đi qua chính lớp dịch vụ chứ không tự ghi vào bảng: mức hoàn phải được chốt bằng đúng
     * công thức mà luồng thật dùng, nếu không màn duyệt sẽ hiện một con số không ai tính ra được.
     */
    private function dungYeuCauHuy(): void
    {
        if (!isset($this->don['choDuyet'])) {
            return;
        }

        app(BookingChangeRequestService::class)->requestCancellation(
            $this->don['choDuyet'],
            'Gia đình có việc đột xuất, mong công ty hỗ trợ hủy và hoàn theo chính sách.',
            $this->customer,
        );
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

        if (!$diemDon) {
            return;
        }

        /*
         * Người ghi điểm danh: ưu tiên người thật sự dẫn S6, nếu chuyến đó chưa ai dẫn thì lấy
         * tạm người đầu danh sách.
         *
         * Cố ý KHÔNG bỏ chạy khi S6 chưa có hướng dẫn viên. Trước đây có, và hậu quả là toàn bộ
         * dữ liệu điểm danh biến mất trong im lặng khi đội hướng dẫn viên mỏng - các chuyến kịch
         * bản chồng ngày nhau nên một người không phủ hết được. Dữ liệu mẫu thiếu mà không báo
         * gì còn tệ hơn dữ liệu mẫu sai: người thử tay sẽ đi tìm lỗi trong mã ứng dụng.
         *
         * checked_by chỉ ghi lại ai bấm nút, không phải luật nghiệp vụ, nên lấy tạm là chấp nhận
         * được; mất cả bộ dữ liệu điểm danh thì không.
         */
        $nguoiGhi = $this->schedules['S6']->guides()->first() ?? $this->guide;

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
                    'changed_by' => $nguoiGhi?->id,
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

    /**
     * In ra danh sách việc cần làm, bằng đúng con số hiện trên màn hình.
     *
     * Không dùng nhãn kịch bản kiểu "chuyến S4" ở đây. Người mở trang quản trị chỉ thấy đơn #37
     * và chuyến #12; bắt họ tự dò xem cái nào ứng với nhãn nào là chỗ mà hướng dẫn thử tay hay
     * đứt gãy nhất.
     */
    private function inHuongDan(): void
    {
        $cmd = $this->command;

        if (!$cmd) {
            return;
        }

        // Gọi tên đúng như nhãn hiện trên màn hình: danh sách đơn ghi "BK-19", danh sách chuyến
        // ghi "#8". Viết khác đi là bắt người thử tay tự dịch thêm một lần nữa.
        $id = fn (string $khoa): string => isset($this->don[$khoa])
            ? 'BK-' . $this->don[$khoa]->id
            : '(không có)';

        $chuyen = fn (string $ma): string => '#' . $this->schedules[$ma]->id
            . ' khởi hành ' . $this->schedules[$ma]->start_date->format('d/m H:i');

        $cmd->newLine();
        $cmd->info('=== TOUR THỬ NGHIỆM NGHIỆP VỤ — LÀM LẦN LƯỢT TỪ TRÊN XUỐNG ===');
        $cmd->newLine();

        $cmd->line(' Đăng nhập:  admin@gmail.com / admin123');
        $cmd->line('             guide@gmail.com / guide123   (Phạm Hoàng Long)');
        $cmd->line('             customer@gmail.com / customer123');
        $cmd->newLine();

        $cmd->line(' HƯỚNG DẪN VIÊN: có ' . $this->guides->count() . ' người, mật khẩu đều là guide123');
        foreach ($this->guides as $nguoi) {
            $soChuyen = $nguoi->assignedSchedules()->count();
            $cmd->line(sprintf('   %-18s %-28s dẫn %d chuyến', $nguoi->name, $nguoi->email, $soChuyen));
        }
        $cmd->newLine();
        $cmd->line(' CÁCH TÌM: mọi đơn kịch bản đều cùng tên khách và cùng tour, nên đừng dò bằng mắt.');
        $cmd->line('   /admin/bookings   -> gõ mã đơn vào ô tìm kiếm, ví dụ  BK-19');
        $cmd->line('   /admin/schedules  -> gõ số chuyến vào ô tìm kiếm, ví dụ  8');
        $cmd->newLine();

        $cmd->comment(' VÒNG 1 — chỉ xem, chưa hủy gì.  Vào /admin/bookings');
        $cmd->line('   Tìm từng mã, mở ra, bấm "Hủy đơn" để đọc bảng dự báo, rồi "Không hủy nữa":');
        $cmd->line('     ' . $id('hoan90') . '  ->  phải thấy mức hoàn 90%');
        $cmd->line('     ' . $id('hoan70') . '  ->  phải thấy mức hoàn 70%');
        $cmd->line('     ' . $id('hoan50') . '  ->  phải thấy mức hoàn 50%');
        $cmd->line('     ' . $id('hoan30') . '  ->  phải thấy mức hoàn 30% + CẢNH BÁO ghế chết');
        $cmd->line('     ' . $id('hoan0') . '  ->  phải thấy mức hoàn 0%');
        $cmd->newLine();

        $cmd->comment(' VÒNG 2 — hủy thật, xem chỗ có về kho không');
        $cmd->line('   1. Hủy ' . $id('hoan50') . ', thuộc chuyến ' . $chuyen('S3'));
        $cmd->line('      -> chỗ TRẢ về kho. Vào /admin/schedules xem chuyến đó: số chỗ đã giảm');
        $cmd->line('   2. Hủy ' . $id('hoan30') . ', thuộc chuyến ' . $chuyen('S4'));
        $cmd->line('      -> GHẾ CHẾT. Xem chuyến đó: số chỗ GIỮ NGUYÊN');
        $cmd->line('   3. Vào /admin/held-seats -> có 2 dòng -> mở lại 1 dòng kèm lý do');
        $cmd->line('      Lúc này số chỗ mới giảm, nhưng chuyến vẫn "Đã đóng bán"');
        $cmd->newLine();

        $cmd->comment(' VÒNG 2b — yêu cầu hủy của khách đã thanh toán (nhóm F)');
        $cmd->line('   1. Khách: /my-bookings -> ' . $id('xinHuy') . ' -> nút "Yêu cầu hủy"');
        $cmd->line('      Xem mức hoàn, nhập lý do, gửi. ĐƠN PHẢI GIỮ NGUYÊN "Đã xác nhận"');
        $cmd->line('   2. Quản trị: /admin/change-requests -> có 2 yêu cầu chờ duyệt');
        $cmd->line('      Mở yêu cầu của ' . $id('choDuyet') . ' -> Duyệt -> đơn mới chuyển sang đã hủy');
        $cmd->line('   3. Mở yêu cầu còn lại -> Từ chối, nhập lý do -> đơn giữ nguyên');
        $cmd->newLine();

        $cmd->comment(' VÒNG 2c — sửa danh sách hành khách (nhóm G)');
        $cmd->line('   1. Khách: /my-bookings -> ' . $id('hoan90') . ' -> nút "Hành khách"');
        $cmd->line('      Còn xa hạn chốt nên sửa được. Thử khai hai người TRÙNG số giấy tờ -> bị chặn');
        $cmd->line('      Thử khai ngày sinh người lớn nhưng chọn loại "Trẻ em" -> bị chặn');
        $cmd->line('   2. Mở ' . $id('hoan0') . ' -> chuyến này đã qua hạn chốt');
        $cmd->line('      Các ô bị khóa, kèm câu giải thích. Cùng một khách, khác thời điểm');
        $cmd->line('   3. Quản trị vẫn sửa được ' . $id('hoan0') . ' qua API admin');
        $cmd->newLine();

        $cmd->comment(' VÒNG 2d — nhật ký thay đổi đơn (nhóm E)');
        $cmd->line('   Sau khi làm vòng 2 và 2b, mở lại bất kỳ đơn nào vừa thao tác ở /admin/bookings');
        $cmd->line('   -> mục "Lịch sử thay đổi" ở cuối, bấm Xem');
        $cmd->line('   Đọc được: ai làm gì, lúc nào, từ trạng thái nào sang trạng thái nào,');
        $cmd->line('   hoàn bao nhiêu, chỗ có về kho không, và lý do');
        $cmd->newLine();

        $cmd->comment(' VÒNG 2e — chuyển chuyến (nhóm I)');
        $cmd->line('   /admin/bookings -> ' . $id('hoan90') . ' -> nút "Chuyển chuyến"');
        $cmd->line('   Chọn chuyến đích, đọc chênh lệch giá tính sẵn, nhập lý do, xác nhận');
        $cmd->line('   Rồi vào /admin/schedules xem: chuyến cũ giảm chỗ, chuyến mới tăng chỗ');
        $cmd->line('   Bỏ tick "Chỉ trong cùng tour" để thấy cả chuyến của tour khác');
        $cmd->newLine();

        $cmd->comment(' VÒNG 2f — ghép chuyến (nhóm L)');
        $cmd->line('   /admin/schedules -> chuyến ' . $chuyen('S10') . ' -> nút "Ghép chuyến"');
        $cmd->line('   Chọn chuyến ' . $chuyen('S11') . ' -> đọc "chuyển 1 đơn, hủy 1 đơn chưa thanh toán"');
        $cmd->line('   Nhập lý do, xác nhận. Sau đó xem lại danh sách chuyến:');
        $cmd->line('     - chuyến nguồn thành "Đã hủy chuyến", số chỗ về 0');
        $cmd->line('     - chuyến đích cộng thêm khách');
        $cmd->line('     - đơn chưa thanh toán bị hủy, KHÔNG bị kéo sang ngày mới');
        $cmd->newLine();

        $cmd->comment(' VÒNG 3 — chặn hủy khi chuyến đang chạy');
        $cmd->line('   1. /admin/bookings -> ' . $id('chuyenDangChay') . ' -> Hủy đơn');
        $cmd->line('      Nút xác nhận phải BỊ KHÓA, kèm lý do chuyến đang chạy');
        $cmd->line('   2. Đăng nhập khách -> /my-bookings -> đơn "Chờ thanh toán" (' . $id('khachTuHuy') . ')');
        $cmd->line('      -> Hủy đơn. Cái này hủy được, vì chưa trả tiền và chuyến chưa khởi hành');
        $cmd->line('      Trang khách không hiện mã đơn, nhưng chỉ có đúng một đơn chờ thanh toán');
        $cmd->newLine();

        $cmd->comment(' VÒNG 4 — điểm danh.  Đăng nhập hướng dẫn viên');
        $cmd->line('   Vào thẳng:  /guide/attendance/' . $this->schedules['S6']->id);
        $cmd->line('   1. Đánh vắng một người, gõ ghi chú DƯỚI 10 ký tự -> bị chặn tại chỗ');
        $cmd->line('   2. Gõ đủ ghi chú -> lưu được -> sửa lại thành có mặt -> lưu lần nữa');
        $cmd->line('   3. Bấm sang điểm dừng của NGÀY MAI rồi thử ghi -> bị từ chối');
        $cmd->line('   4. Quay lại quản trị: /admin/tour-schedules/' . $this->schedules['S6']->id . '/attendance');
        $cmd->line('      Xem trạng thái mới và dấu vết lần sửa');
        $cmd->newLine();

        $cmd->comment(' VÒNG 5 — bốn lệnh, chạy lần lượt và đọc kỹ dòng in ra');
        $cmd->line('   php artisan schedules:confirm-ready');
        $cmd->line('     -> chuyến ' . $chuyen('S4') . ' thiếu khách: chỉ cảnh báo');
        $cmd->line('     -> chuyến ' . $chuyen('S9') . ' đủ khách: được chốt');
        $cmd->line('   php artisan bookings:release-expired');
        $cmd->line('     -> đơn ' . $id('quaHan') . ' tự hủy và TRẢ chỗ, dù đã qua hạn chốt');
        $cmd->line('   php artisan bookings:finalize-completed');
        $cmd->line('     -> chuyến ' . $chuyen('S7') . ': 1 đơn KHÔNG CÓ MẶT, 2 đơn ĐÃ HOÀN THÀNH');
        $cmd->line('   php artisan bookings:expire-stale-holds');
        $cmd->line('     -> dọn đơn chờ thanh toán còn treo của chuyến đã kết thúc');
        $cmd->line('   php artisan bookings:check-seat-consistency');
        $cmd->line('     -> phải báo "Số chỗ của mọi chuyến đều khớp"');
        $cmd->newLine();

        $cmd->comment(' VÒNG 7 — nhiều hướng dẫn viên.  Vào /admin/schedules');
        $cmd->line('   Cột "Hướng dẫn viên" đang có ba kiểu, xem trước cho quen mắt:');
        $cmd->line('     ' . $chuyen('S1') . '  ->  3 người');
        $cmd->line('     ' . $chuyen('S6') . '  ->  2 người, và đang khởi hành');
        $cmd->line('     ' . $chuyen('S8') . '  ->  chưa phân công ai');
        $cmd->newLine();
        $cmd->line('   Bấm "Sửa" ở ' . $chuyen('S2') . ' rồi tick thêm người -> lưu được, không có cảnh báo nào');
        $cmd->line('     (hệ thống KHÔNG tính hộ bao nhiêu khách cần bao nhiêu HDV, đó là việc của bạn)');
        $cmd->newLine();
        $cmd->line('   Thử luật DUY NHẤT còn lại — một người không đứng ở hai đoàn cùng lúc:');
        $cmd->line('     ở ' . $chuyen('S5') . ' tick người đang dẫn ' . $chuyen('S6') . ' -> phải bị từ chối,');
        $cmd->line('     và những người khác vừa tick cũng KHÔNG được lưu (được ăn cả ngã về không)');
        $cmd->newLine();
        $cmd->line('   Đăng nhập bằng một HDV của ' . $chuyen('S6') . ' -> vào được màn điểm danh của chuyến đó.');
        $cmd->line('   Đăng nhập bằng người KHÔNG thuộc chuyến -> không thấy chuyến đó.');
        $cmd->newLine();

        $cmd->comment(' THÊM (không bắt buộc)');
        $cmd->line('   Mở lại đơn hủy nhầm: /admin/bookings -> ' . $id('moiHuy') . ' -> nút Mở lại');
        $cmd->line('   Xem mức hoàn không cần đăng nhập: /booking-success/' . ($this->don['hoan90']->public_token ?? ''));
        $cmd->line('   Danh sách đoàn theo nhóm: /admin/schedules -> ' . $chuyen('S6') . ' -> nút "Danh sách đoàn"');
        $cmd->line('     -> bấm vào từng nhóm để xem nhóm đó gồm những ai');
        $cmd->newLine();

        $this->inBangChuyen();

        $cmd->line(' Giải thích vì sao từng bước ra kết quả đó: docs/nghiep-vu/15-verify-a-den-h.md');
        $cmd->newLine();
    }

    /** Bảng tra cứu chín chuyến, để đối chiếu khi màn hình không giống mô tả. */
    private function inBangChuyen(): void
    {
        $rows = [];

        foreach ($this->schedules as $ma => $schedule) {
            $gio = (int) round(now()->diffInHours($schedule->start_date, false));

            $rows[] = [
                '#' . $schedule->id,
                $ma,
                $gio >= 0 ? "còn {$gio}h" : 'đã qua ' . abs($gio) . 'h',
                ScheduleStatus::from($schedule->getRawOriginal('status'))->label(),
                (int) $schedule->booked_people . '/' . (int) $schedule->max_people,
            ];
        }

        $this->command?->table(
            ['Chuyến', 'Mã trong tài liệu', 'Cách hiện tại', 'Trạng thái', 'Chỗ'],
            $rows,
        );
    }
}
