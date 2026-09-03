<?php

namespace Database\Seeders;

use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Service;
use App\Models\Tour;
use App\Models\TourImage;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\ScheduleGuideService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Danh mục tour để thử tay và để demo.
 *
 * ## Vì sao nhiều tour đến vậy
 *
 * Bốn tour, mỗi tour một hai chuyến, tất cả cùng ở trạng thái mở bán và cùng nằm trong hai tuần
 * tới — đó là dữ liệu đủ để chụp ảnh màn hình, không đủ để thử. Bộ lọc theo vùng chỉ có bốn lựa
 * chọn, phân trang không bao giờ sang trang hai, lịch khởi hành tháng nào cũng trống, và năm
 * trạng thái chuyến còn lại không có mẫu nào để xem.
 *
 * Danh mục dưới đây trải trên ba miền, sáu độ dài (một ngày tới sáu ngày), cả hai mô hình kinh
 * doanh, và các chuyến rải từ nửa năm trước tới bốn tháng tới.
 *
 * ## Mỗi trạng thái đều có ít nhất một mẫu
 *
 * Tour: đang bán, ngừng bán, hết chỗ, và một tour đã xóa mềm nằm trong thùng rác.
 * Chuyến: mở bán, đóng bán, đã chốt, đang đi, đã kết thúc, đã hủy.
 *
 * ## Số chỗ đã bán KHÔNG đặt ở đây
 *
 * `booked_people` luôn để 0. Con số ấy phải suy ra từ đơn thật, và `DemoBookingSeeder` chạy ngay
 * sau seeder này sẽ dựng đơn rồi cộng lại. Đặt tay một con số không có đơn nào đứng sau thì lệnh
 * `bookings:check-seat-consistency` sẽ báo lệch — đúng như nó phải báo.
 */
class SampleTourSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', 'admin')->first() ?? User::query()->first();

        if (! $admin) {
            return;
        }

        // Cả đội, không riêng người đầu: các tour mẫu có chuyến sát ngày nhau, gán chung một
        // người thì dữ liệu mẫu vi phạm chính luật chống trùng lịch mà hệ thống đang chặn.
        $guides = User::query()
            ->where('role', 'guide')
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        // Danh mục nay dùng chung với hồ sơ năng lực hướng dẫn viên nên ở seeder riêng. Gọi lại
        // ở đây để chạy lẻ `--class=SampleTourSeeder` vẫn đủ dữ liệu; seeder đó idempotent.
        $this->call(CategorySeeder::class);

        $categories = Category::query()->get();

        $services = collect([
            'Xe đưa đón',
            'Khách sạn 4 sao',
            'Ăn sáng',
            'Hướng dẫn viên',
            'Vé tham quan',
            'Bảo hiểm du lịch',
            'Vé máy bay',
        ])->map(fn (string $ten) => Service::query()->firstOrCreate(['name' => $ten], []));

        $danhMuc = $this->danhMucTour();

        // Tour KHÔNG gắn chính sách hủy riêng: cả hệ thống dùng chung một bảng phí, và đơn chép
        // bảng đang có hiệu lực vào chính nó lúc đặt. Xem chú thích ở `Tour`.
        DB::transaction(function () use ($admin, $guides, $categories, $services, $danhMuc) {
            foreach ($danhMuc as $data) {
                $tour = Tour::withTrashed()->updateOrCreate(
                    ['slug' => $data['slug']],
                    [
                        'admin_id' => $admin->id,
                        'title' => $data['title'],
                        'description' => $data['description'],
                        'adult_price' => $data['adult_price'],
                        'child_price' => (int) round($data['adult_price'] * 0.7),
                        'infant_price' => $data['infant_price'] ?? 0,
                        'thumbnail' => $data['thumbnail'],
                        'number_of_days' => $data['days'],
                        'number_of_nights' => max(0, $data['days'] - 1),
                        'start_location' => $data['from'],
                        'end_location' => $data['to'],
                        'vehicle_info' => $data['vehicle'],
                        'pickup_location' => $data['pickup'],
                        'is_featured' => $data['featured'] ?? false,
                        'status' => $data['status'] ?? 'active',
                        'type' => ($data['type'] ?? TourType::Shared)->value,
                    ]
                );

                /*
                 * Tour trong thùng rác: xóa mềm SAU khi dựng xong nội dung, để màn khôi phục có
                 * thứ thật để khôi phục. Tour đang bán thì gỡ khỏi thùng rác nếu lần seed trước
                 * đã bỏ nó vào.
                 */
                if ($data['deleted'] ?? false) {
                    $tour->deleted_at ?: $tour->delete();
                } elseif ($tour->trashed()) {
                    $tour->restore();
                }

                $tour->categories()->sync(
                    $categories->whereIn('slug', $data['categories'])->pluck('id')->all()
                );

                $tour->services()->sync(
                    $services->whereIn('name', $data['services'])->pluck('id')->all()
                );

                $this->dungLichTrinh($tour, $data);
                $this->dungAnh($tour, $data);
                $this->dungChuyen($tour, $data, $guides);
            }
        });

        $this->inTomTat();
    }

    /**
     * Lịch trình từng ngày, kèm điểm dừng.
     *
     * Khai một dòng cho mỗi ngày; ngày nào không khai thì sinh mục chung, vì tour thiếu ngày
     * trong lịch trình là dữ liệu mà chính biểu mẫu quản trị sẽ từ chối lưu.
     */
    private function dungLichTrinh(Tour $tour, array $data): void
    {
        /*
         * Chỉ dựng lại khi chưa có. Xóa rồi tạo mới sẽ kéo theo `itinerary_checkpoints` và toàn
         * bộ `passenger_checkins` gắn với chúng — tức mất sạch dữ liệu điểm danh của các chuyến
         * đã đi, chỉ vì chạy lại seeder.
         */
        if ($tour->itineraries()->exists()) {
            return;
        }

        $mucKhai = $data['itineraries'] ?? [];

        for ($ngay = 1; $ngay <= $data['days']; $ngay++) {
            $muc = $mucKhai[$ngay - 1] ?? [
                'title' => 'Ngày ' . $ngay . ' - ' . $data['to'],
                'content' => 'Hoạt động tham quan và nghỉ ngơi theo chương trình.',
            ];

            $itinerary = $tour->itineraries()->create([
                'day_number' => $ngay,
                'title' => $muc['title'],
                'start_point' => $muc['start_point'] ?? ($ngay === 1 ? $data['from'] : $data['to']),
                'end_point' => $muc['end_point'] ?? ($ngay === $data['days'] ? $data['to'] : $data['to']),
                'route_points' => $muc['route_points'] ?? null,
                'rest_stops' => $muc['rest_stops'] ?? null,
                'content' => $muc['content'],
            ]);

            /*
             * Mỗi ngày ít nhất một điểm dừng. Không có điểm dừng thì màn điểm danh của hướng dẫn
             * viên rỗng và trông như tính năng chưa chạy, trong khi thực ra là chưa khai dữ liệu.
             * Tọa độ đặt ở trung tâm điểm đến để luồng tải ảnh check-in có mốc đối chiếu.
             */
            $itinerary->checkpoints()->create([
                'name' => $ngay === 1 ? 'Điểm tập trung khởi hành' : 'Điểm tập trung ngày ' . $ngay,
                'description' => $ngay === 1 ? 'Hướng dẫn viên đón đoàn, điểm danh trước khi xuất phát.' : null,
                'sequence' => 1,
                'is_required_photo' => $ngay === 1,
                'latitude' => $data['lat'] ?? 21.0245,
                'longitude' => $data['lng'] ?? 105.8572,
            ]);

            // Ngày giữa hành trình có thêm một điểm tham quan, để thấy chuyến nhiều điểm danh.
            if ($ngay > 1 && $ngay < $data['days']) {
                $itinerary->checkpoints()->create([
                    'name' => 'Điểm tham quan chính ngày ' . $ngay,
                    'sequence' => 2,
                    'is_required_photo' => false,
                    'latitude' => $data['lat'] ?? 21.0245,
                    'longitude' => $data['lng'] ?? 105.8572,
                ]);
            }
        }
    }

    private function dungAnh(Tour $tour, array $data): void
    {
        $tour->images()->delete();

        foreach ($data['images'] ?? [$data['thumbnail']] as $url) {
            TourImage::query()->create(['tour_id' => $tour->id, 'image_path' => $url]);
        }
    }

    /**
     * Các chuyến khởi hành của một tour.
     *
     * Mỗi dòng khai `cach` — số ngày lệch so với hôm nay, âm là quá khứ — nên chạy seeder lúc nào
     * cũng ra đúng tình huống ấy, thay vì ngày tháng cứng trôi dần thành quá khứ hết.
     */
    private function dungChuyen(Tour $tour, array $data, $guides): void
    {
        /*
         * Chỉ dọn những chuyến CHƯA có đơn nào. Chuyến đã có khách mà bị xóa thì đơn của họ mất
         * chuyến, và mọi màn hình đọc `booking->schedule` sẽ vỡ.
         */
        $coDon = Booking::query()
            ->whereIn('tour_schedule_id', $tour->schedules()->pluck('id'))
            ->pluck('tour_schedule_id')
            ->unique();

        $tour->schedules()->whereNotIn('id', $coDon)->delete();

        if ($tour->schedules()->exists()) {
            return;
        }

        $soNgay = max(1, (int) $data['days']);
        $laRieng = ($data['type'] ?? TourType::Shared) === TourType::Private;

        foreach ($data['schedules'] as $mau) {
            $batDau = now()->addDays($mau['cach'])->setTimeFromTimeString($mau['gio'] ?? '06:00');
            $trangThai = $mau['trang_thai'] ?? ScheduleStatus::Open;

            $payload = [
                'start_date' => $batDau,
                'end_date' => $batDau->copy()->addDays($soNgay - 1)->endOfDay(),
                'booking_deadline' => $batDau->copy()->subDays(
                    (int) config('booking.booking_deadline_days', 3)
                ),
                'max_people' => $mau['max'],
                // Tour riêng không có mức tối thiểu: một đoàn đặt trọn chuyến thì đoàn ấy quyết
                // chuyến có chạy hay không, không phải con số nào khác.
                'min_people' => $laRieng ? 1 : ($mau['min'] ?? (int) ceil($mau['max'] * 0.4)),
                'booked_people' => 0,
                'status' => $trangThai->value,
            ];

            if ($trangThai === ScheduleStatus::Confirmed) {
                $payload['confirmed_at'] = $batDau->copy()->subDays(4);
            }

            if ($trangThai === ScheduleStatus::Cancelled) {
                $payload['cancelled_at'] = now()->subDays(2);
                $payload['cancelled_reason'] = $mau['ly_do'] ?? 'Không đủ khách tối thiểu, đã báo và hoàn tiền cho khách.';
            }

            $schedule = $tour->schedules()->create($payload);

            $this->phanCongNguoiRanh($schedule, $tour, $guides, $mau['so_hdv'] ?? 1);
        }
    }

    /**
     * Gán hướng dẫn viên đang trống lịch cho chuyến.
     *
     * Không lấy cứng người đầu danh sách: các tour mẫu có chuyến trùng ngày nhau, mà một người
     * thì không đứng ở hai đoàn cùng lúc. Dùng lại ScheduleGuideService để phép so ở đây giống
     * hệt lúc chạy thật, thay vì chép thành đoạn mã thứ hai rồi lệch dần.
     *
     * Không ai rảnh thì để trống — "chưa phân công" là trạng thái hợp lệ, còn gán bừa thì tạo ra
     * dữ liệu mà chính hệ thống sẽ từ chối nếu người dùng làm y hệt trên giao diện.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $guides
     */
    private function phanCongNguoiRanh(TourSchedule $schedule, Tour $tour, $guides, int $soNguoi): void
    {
        if ($guides->isEmpty() || $soNguoi < 1) {
            return;
        }

        $service = app(ScheduleGuideService::class);
        [$start, $end] = $service->periodOf($schedule->setRelation('tour', $tour));

        $chon = [];

        foreach ($guides as $nguoi) {
            if (count($chon) >= $soNguoi) {
                break;
            }

            if ($service->conflictFor($nguoi->id, $start, $end, $schedule->getKey())) {
                continue;
            }

            $chon[] = $nguoi->id;
            $schedule->guides()->sync($chon);
        }
    }

    private function inTomTat(): void
    {
        $cmd = $this->command;

        if (!$cmd) {
            return;
        }

        $theoTrangThai = TourSchedule::query()
            ->selectRaw('status, count(*) as so')
            ->groupBy('status')
            ->pluck('so', 'status');

        $cmd->info(sprintf(
            'Danh mục: %d tour đang bán, %d ngừng bán, %d hết chỗ, %d trong thùng rác.',
            Tour::query()->where('status', 'active')->count(),
            Tour::query()->where('status', 'inactive')->count(),
            Tour::query()->where('status', 'full')->count(),
            Tour::onlyTrashed()->count(),
        ));

        $cmd->line('  Chuyến khởi hành: ' . $theoTrangThai
            ->map(fn ($so, $tt) => "{$tt}={$so}")
            ->implode(', '));
    }

    /**
     * Danh mục tour.
     *
     * `cach` là số ngày lệch so với lúc chạy seeder. Âm là chuyến đã qua.
     *
     * @return array<int, array<string, mixed>>
     */
    private function danhMucTour(): array
    {
        return [
            // ── Bốn tour gốc. Giữ nguyên slug vì tài liệu và các seeder khác trỏ tới. ────────
            [
                'slug' => 'tour-ha-long-3n2d',
                'title' => 'Tour Hạ Long 3N2Đ',
                'description' => 'Khám phá vịnh Hạ Long, nghỉ dưỡng và trải nghiệm hải trình ngắn ngày phù hợp gia đình.',
                'adult_price' => 3190000,
                'days' => 3,
                'from' => 'Hà Nội',
                'to' => 'Hạ Long',
                'vehicle' => 'Xe giường nằm 34 chỗ đời mới, có wifi và nước uống',
                'pickup' => 'Nhà hát Lớn Hà Nội - Số 1 Tràng Tiền, Hoàn Kiếm (có mặt trước giờ khởi hành 30 phút)',
                'thumbnail' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e',
                'images' => [
                    'https://images.unsplash.com/photo-1507525428034-b723cf961d3e',
                    'https://images.unsplash.com/photo-1482192596544-9eb780fc7f66',
                ],
                'featured' => true,
                'categories' => ['bien-dao', 'nghi-duong'],
                'services' => ['Xe đưa đón', 'Khách sạn 4 sao', 'Ăn sáng', 'Bảo hiểm du lịch'],
                'lat' => 20.9101,
                'lng' => 107.1839,
                'itineraries' => [
                    ['title' => 'Hà Nội - Hạ Long', 'content' => 'Di chuyển từ Hà Nội, nhận phòng và tự do tham quan bãi Cháy.', 'route_points' => 'Hải Dương, Uông Bí'],
                    ['title' => 'Du thuyền vịnh Hạ Long', 'content' => 'Tham quan hang Sửng Sốt, đảo Ti Tốp, chèo kayak.'],
                    ['title' => 'Trả phòng - về Hà Nội', 'content' => 'Ăn sáng, mua đặc sản và quay về Hà Nội.'],
                ],
                'schedules' => [
                    ['cach' => -75, 'gio' => '05:30', 'max' => 20, 'trang_thai' => ScheduleStatus::Completed],
                    ['cach' => -40, 'gio' => '05:30', 'max' => 20, 'trang_thai' => ScheduleStatus::Completed],
                    ['cach' => 7, 'gio' => '05:30', 'max' => 20, 'so_hdv' => 2],
                    ['cach' => 14, 'gio' => '05:30', 'max' => 20],
                    ['cach' => 45, 'gio' => '05:30', 'max' => 20],
                    ['cach' => 80, 'gio' => '05:30', 'max' => 20],
                ],
            ],
            [
                'slug' => 'tour-da-nang-hoi-an-4n3d',
                'title' => 'Tour Đà Nẵng - Hội An 4N3Đ',
                'description' => 'Combo biển, phố cổ và ẩm thực miền Trung với lịch trình cân bằng giữa nghỉ dưỡng và khám phá.',
                'adult_price' => 3890000,
                'days' => 4,
                'from' => 'TP. Hồ Chí Minh',
                'to' => 'Đà Nẵng',
                'vehicle' => 'Vé máy bay khứ hồi + xe du lịch 29 chỗ tại điểm đến',
                'pickup' => 'Ga quốc nội, sân bay Tân Sơn Nhất - Cột số 9 (tập trung trước giờ bay 2 tiếng)',
                'thumbnail' => 'https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86',
                'images' => [
                    'https://images.unsplash.com/photo-1512813195386-6cf811ad3542',
                    'https://images.unsplash.com/photo-1543877087-ebf71fde2be1',
                ],
                'categories' => ['nghi-duong', 'kham-pha'],
                'services' => ['Vé máy bay', 'Xe đưa đón', 'Hướng dẫn viên', 'Ăn sáng'],
                'lat' => 16.0544,
                'lng' => 108.2022,
                'itineraries' => [
                    ['title' => 'Bay tới Đà Nẵng', 'content' => 'Nhận phòng, tắm biển Mỹ Khê, tự do buổi tối.'],
                    ['title' => 'Bà Nà Hills - Cầu Vàng', 'content' => 'Cáp treo lên Bà Nà, tham quan Làng Pháp và Cầu Vàng.'],
                    ['title' => 'Phố cổ Hội An', 'content' => 'Chùa Cầu, nhà cổ Tấn Ký, thả đèn hoa đăng sông Hoài.'],
                    ['title' => 'Ngũ Hành Sơn - bay về', 'content' => 'Tham quan Ngũ Hành Sơn, mua đặc sản và ra sân bay.'],
                ],
                'schedules' => [
                    ['cach' => -50, 'gio' => '08:00', 'max' => 25, 'trang_thai' => ScheduleStatus::Completed],
                    // Qua hạn chốt (3 ngày) nên không nhận đặt mới nữa.
                    ['cach' => 2, 'gio' => '08:00', 'max' => 25, 'trang_thai' => ScheduleStatus::Closed],
                    ['cach' => 10, 'gio' => '08:00', 'max' => 25],
                    ['cach' => 38, 'gio' => '08:00', 'max' => 25],
                    ['cach' => 66, 'gio' => '08:00', 'max' => 25],
                ],
            ],
            [
                'slug' => 'tour-sapa-fansipan-2n1d',
                'title' => 'Tour Sapa - Fansipan 2N1Đ',
                'description' => 'Săn mây Fansipan, dạo bản Cát Cát và thưởng thức ẩm thực Tây Bắc trong hai ngày cuối tuần.',
                'adult_price' => 1890000,
                'days' => 2,
                'from' => 'Hà Nội',
                'to' => 'Sapa',
                'vehicle' => 'Xe giường nằm cao cấp 22 chỗ, khởi hành đêm',
                'pickup' => 'Bến xe Mỹ Đình - Cổng chính, Nam Từ Liêm (có mặt trước giờ khởi hành 30 phút)',
                'thumbnail' => 'https://images.unsplash.com/photo-1570366583862-f91883984fde',
                'featured' => true,
                'categories' => ['kham-pha'],
                'services' => ['Xe đưa đón', 'Hướng dẫn viên', 'Ăn sáng', 'Vé tham quan'],
                'lat' => 22.3364,
                'lng' => 103.8438,
                'itineraries' => [
                    ['title' => 'Hà Nội - Sapa - Bản Cát Cát', 'content' => 'Di chuyển lên Sapa, nhận phòng, tham quan bản Cát Cát.', 'route_points' => 'Lào Cai'],
                    ['title' => 'Chinh phục Fansipan', 'content' => 'Đi cáp treo Fansipan, ăn trưa và trở về Hà Nội.'],
                ],
                'schedules' => [
                    ['cach' => -22, 'gio' => '21:00', 'max' => 22, 'trang_thai' => ScheduleStatus::Completed],
                    // Đã chốt chắc chắn chạy: đủ khách tối thiểu và sắp tới ngày đi.
                    ['cach' => 3, 'gio' => '21:00', 'max' => 22, 'trang_thai' => ScheduleStatus::Confirmed],
                    ['cach' => 9, 'gio' => '21:00', 'max' => 22],
                    ['cach' => 21, 'gio' => '21:00', 'max' => 22, 'trang_thai' => ScheduleStatus::Cancelled, 'ly_do' => 'Sạt lở đường lên Sapa, hủy chuyến và hoàn tiền toàn bộ.'],
                    ['cach' => 30, 'gio' => '21:00', 'max' => 22],
                    ['cach' => 60, 'gio' => '21:00', 'max' => 22],
                ],
            ],
            [
                'slug' => 'tour-phu-quoc-3n2d',
                'title' => 'Tour Phú Quốc 3N2Đ',
                'description' => 'Nghỉ dưỡng đảo ngọc: lặn ngắm san hô 4 đảo, chợ đêm Dương Đông và hoàng hôn Sunset Sanato.',
                'adult_price' => 4590000,
                'days' => 3,
                'from' => 'TP. Hồ Chí Minh',
                'to' => 'Phú Quốc',
                'vehicle' => 'Vé máy bay khứ hồi + xe đưa đón sân bay tại Phú Quốc',
                'pickup' => 'Ga quốc nội, sân bay Tân Sơn Nhất - Cột số 11 (tập trung trước giờ bay 2 tiếng)',
                'thumbnail' => 'https://images.unsplash.com/photo-1540541338287-41700207dee6',
                'categories' => ['bien-dao', 'nghi-duong'],
                'services' => ['Vé máy bay', 'Xe đưa đón', 'Khách sạn 4 sao', 'Ăn sáng', 'Hướng dẫn viên'],
                'lat' => 10.2270,
                'lng' => 103.9670,
                'itineraries' => [
                    ['title' => 'Bay đến Phú Quốc', 'content' => 'Bay đến đảo, nhận phòng resort, tự do tắm biển.'],
                    ['title' => 'Tour 4 đảo', 'content' => 'Lặn ngắm san hô, câu cá, ăn trưa hải sản trên tàu.'],
                    ['title' => 'Chợ Dương Đông - trở về', 'content' => 'Mua đặc sản, trả phòng và bay về TP.HCM.'],
                ],
                'schedules' => [
                    // Đoàn đang trên đường: mở được màn điểm danh của hướng dẫn viên.
                    ['cach' => -1, 'gio' => '06:30', 'max' => 24, 'trang_thai' => ScheduleStatus::InProgress, 'so_hdv' => 2],
                    ['cach' => 12, 'gio' => '06:30', 'max' => 24],
                    ['cach' => 33, 'gio' => '06:30', 'max' => 24],
                    ['cach' => 75, 'gio' => '06:30', 'max' => 24],
                ],
            ],

            // ── Tour một ngày: nhiều chuyến, dùng để thử lịch dày và phân trang. ─────────────
            [
                'slug' => 'tour-ninh-binh-1n',
                'title' => 'Tour Ninh Bình 1 ngày',
                'description' => 'Tràng An - Hang Múa - Bái Đính trong ngày, khởi hành sáng sớm và về trong tối.',
                'adult_price' => 890000,
                'days' => 1,
                'from' => 'Hà Nội',
                'to' => 'Ninh Bình',
                'vehicle' => 'Xe 45 chỗ đời mới',
                'pickup' => 'Nhà hát Lớn Hà Nội - Số 1 Tràng Tiền (có mặt lúc 6h00)',
                'thumbnail' => 'https://images.unsplash.com/photo-1528127269322-539801943592',
                'featured' => true,
                'categories' => ['kham-pha'],
                'services' => ['Xe đưa đón', 'Hướng dẫn viên', 'Vé tham quan'],
                'lat' => 20.2506,
                'lng' => 105.9745,
                'itineraries' => [
                    ['title' => 'Tràng An - Hang Múa - Bái Đính', 'content' => 'Đi thuyền Tràng An, leo Hang Múa ngắm toàn cảnh, viếng chùa Bái Đính.', 'route_points' => 'Phủ Lý'],
                ],
                'schedules' => [
                    ['cach' => -14, 'gio' => '06:00', 'max' => 40, 'trang_thai' => ScheduleStatus::Completed],
                    ['cach' => -7, 'gio' => '06:00', 'max' => 40, 'trang_thai' => ScheduleStatus::Completed],
                    ['cach' => 5, 'gio' => '06:00', 'max' => 40],
                    ['cach' => 12, 'gio' => '06:00', 'max' => 40],
                    ['cach' => 19, 'gio' => '06:00', 'max' => 40],
                    ['cach' => 26, 'gio' => '06:00', 'max' => 40],
                    ['cach' => 40, 'gio' => '06:00', 'max' => 40],
                    ['cach' => 54, 'gio' => '06:00', 'max' => 40],
                ],
            ],

            // ── Miền núi phía Bắc, cung dài. ────────────────────────────────────────────────
            [
                'slug' => 'tour-ha-giang-4n3d',
                'title' => 'Tour Hà Giang - Đồng Văn 4N3Đ',
                'description' => 'Cung đường đá Hà Giang: đèo Mã Pí Lèng, sông Nho Quế, cao nguyên đá Đồng Văn.',
                'adult_price' => 3490000,
                'days' => 4,
                'from' => 'Hà Nội',
                'to' => 'Hà Giang',
                'vehicle' => 'Xe limousine 16 chỗ, đường đèo',
                'pickup' => 'Bến xe Mỹ Đình - Cổng chính (có mặt lúc 21h00)',
                'thumbnail' => 'https://images.unsplash.com/photo-1528181304800-259b08848526',
                'categories' => ['kham-pha'],
                'services' => ['Xe đưa đón', 'Hướng dẫn viên', 'Ăn sáng', 'Bảo hiểm du lịch'],
                'lat' => 23.1120,
                'lng' => 105.2790,
                'schedules' => [
                    ['cach' => -35, 'gio' => '21:00', 'max' => 16, 'trang_thai' => ScheduleStatus::Completed],
                    ['cach' => 18, 'gio' => '21:00', 'max' => 16],
                    ['cach' => 50, 'gio' => '21:00', 'max' => 16],
                    ['cach' => 95, 'gio' => '21:00', 'max' => 16],
                ],
            ],

            // ── Miền Tây. ───────────────────────────────────────────────────────────────────
            [
                'slug' => 'tour-mien-tay-3n2d',
                'title' => 'Tour Miền Tây - Cần Thơ - Châu Đốc 3N2Đ',
                'description' => 'Chợ nổi Cái Răng, rừng tràm Trà Sư và miếu Bà Chúa Xứ trong hành trình sông nước.',
                'adult_price' => 2690000,
                'days' => 3,
                'from' => 'TP. Hồ Chí Minh',
                'to' => 'Cần Thơ',
                'vehicle' => 'Xe 29 chỗ + thuyền tham quan',
                'pickup' => 'Nhà văn hóa Thanh Niên - 4 Phạm Ngọc Thạch, Quận 1',
                'thumbnail' => 'https://images.unsplash.com/photo-1509233725247-49e657c54213',
                'categories' => ['kham-pha', 'nghi-duong'],
                'services' => ['Xe đưa đón', 'Hướng dẫn viên', 'Ăn sáng'],
                'lat' => 10.0452,
                'lng' => 105.7469,
                'schedules' => [
                    ['cach' => -60, 'gio' => '06:00', 'max' => 29, 'trang_thai' => ScheduleStatus::Completed],
                    ['cach' => 8, 'gio' => '06:00', 'max' => 29],
                    ['cach' => 30, 'gio' => '06:00', 'max' => 29],
                    ['cach' => 58, 'gio' => '06:00', 'max' => 29],
                ],
            ],

            // ── Duyên hải Nam Trung Bộ. ─────────────────────────────────────────────────────
            [
                'slug' => 'tour-quy-nhon-4n3d',
                'title' => 'Tour Quy Nhơn - Phú Yên 4N3Đ',
                'description' => 'Kỳ Co, Eo Gió, Gành Đá Đĩa và những bãi biển còn vắng của Nam Trung Bộ.',
                'adult_price' => 4190000,
                'days' => 4,
                'from' => 'Hà Nội',
                'to' => 'Quy Nhơn',
                'vehicle' => 'Vé máy bay khứ hồi + xe 29 chỗ',
                'pickup' => 'Sân bay Nội Bài - Ga quốc nội T1',
                'thumbnail' => 'https://images.unsplash.com/photo-1526772662000-3f88f10405ff',
                'categories' => ['bien-dao', 'nghi-duong'],
                'services' => ['Vé máy bay', 'Xe đưa đón', 'Khách sạn 4 sao', 'Ăn sáng'],
                'lat' => 13.7830,
                'lng' => 109.2196,
                'schedules' => [
                    ['cach' => 25, 'gio' => '07:00', 'max' => 26],
                    ['cach' => 55, 'gio' => '07:00', 'max' => 26],
                    ['cach' => 110, 'gio' => '07:00', 'max' => 26],
                ],
            ],

            // ── Đảo phía Nam. ───────────────────────────────────────────────────────────────
            [
                'slug' => 'tour-con-dao-3n2d',
                'title' => 'Tour Côn Đảo 3N2Đ',
                'description' => 'Viếng nghĩa trang Hàng Dương, tắm biển Đầm Trầu và lặn ngắm san hô hòn Bảy Cạnh.',
                'adult_price' => 5290000,
                'days' => 3,
                'from' => 'TP. Hồ Chí Minh',
                'to' => 'Côn Đảo',
                'vehicle' => 'Vé máy bay khứ hồi + xe điện trên đảo',
                'pickup' => 'Ga quốc nội, sân bay Tân Sơn Nhất - Cột số 4',
                'thumbnail' => 'https://images.unsplash.com/photo-1559827260-dc66d52bef19',
                'categories' => ['bien-dao'],
                'services' => ['Vé máy bay', 'Xe đưa đón', 'Hướng dẫn viên', 'Bảo hiểm du lịch'],
                'lat' => 8.6833,
                'lng' => 106.6083,
                'schedules' => [
                    ['cach' => 15, 'gio' => '05:00', 'max' => 18],
                    ['cach' => 42, 'gio' => '05:00', 'max' => 18],
                    ['cach' => 88, 'gio' => '05:00', 'max' => 18],
                ],
            ],

            // ── Tây Nguyên, cung dài nhất. ──────────────────────────────────────────────────
            [
                'slug' => 'tour-tay-nguyen-5n4d',
                'title' => 'Tour Tây Nguyên 5N4Đ',
                'description' => 'Buôn Ma Thuột - Pleiku - Kon Tum: thác Dray Nur, Biển Hồ và nhà rông Kon Klor.',
                'adult_price' => 5890000,
                'days' => 5,
                'from' => 'TP. Hồ Chí Minh',
                'to' => 'Buôn Ma Thuột',
                'vehicle' => 'Xe 29 chỗ, cung đường Tây Nguyên',
                'pickup' => 'Nhà văn hóa Thanh Niên - 4 Phạm Ngọc Thạch, Quận 1',
                'thumbnail' => 'https://images.unsplash.com/photo-1552465011-b4e21bf6e79a',
                'categories' => ['kham-pha'],
                'services' => ['Xe đưa đón', 'Hướng dẫn viên', 'Ăn sáng', 'Bảo hiểm du lịch'],
                'lat' => 12.6667,
                'lng' => 108.0500,
                'schedules' => [
                    ['cach' => 28, 'gio' => '05:30', 'max' => 25],
                    ['cach' => 70, 'gio' => '05:30', 'max' => 25],
                ],
            ],

            // ── Tour riêng: một đoàn đặt trọn chuyến, không có mức tối thiểu, không ghép. ────
            [
                'slug' => 'tour-hue-quang-binh-rieng-4n3d',
                'title' => 'Tour riêng Huế - Quảng Bình 4N3Đ',
                'description' => 'Chuyến dành riêng cho một đoàn: Đại Nội, lăng Khải Định, động Phong Nha. Lịch trình co giãn theo yêu cầu.',
                'adult_price' => 6490000,
                'days' => 4,
                'from' => 'Hà Nội',
                'to' => 'Huế',
                'vehicle' => 'Xe riêng 16 chỗ theo đoàn',
                'pickup' => 'Đón tận nơi trong nội thành Hà Nội',
                'thumbnail' => 'https://images.unsplash.com/photo-1583417319070-4a69db38a482',
                'type' => TourType::Private,
                'categories' => ['nghi-duong', 'kham-pha'],
                'services' => ['Xe đưa đón', 'Hướng dẫn viên', 'Khách sạn 4 sao', 'Ăn sáng'],
                'lat' => 16.4637,
                'lng' => 107.5909,
                'schedules' => [
                    ['cach' => 20, 'gio' => '06:00', 'max' => 16],
                    ['cach' => 48, 'gio' => '06:00', 'max' => 16],
                ],
            ],

            // ── Cung dài nhất danh mục, đặt xa để thử lịch nhiều tháng tới. ─────────────────
            [
                'slug' => 'tour-xuyen-viet-6n5d',
                'title' => 'Tour Xuyên Việt 6N5Đ',
                'description' => 'Hà Nội - Huế - Đà Nẵng - Hội An - Nha Trang: cung dài đi hết miền Trung.',
                'adult_price' => 8990000,
                'days' => 6,
                'from' => 'Hà Nội',
                'to' => 'Nha Trang',
                'vehicle' => 'Xe giường nằm + tàu hỏa chặng dài',
                'pickup' => 'Ga Hà Nội - 120 Lê Duẩn',
                'thumbnail' => 'https://images.unsplash.com/photo-1528127269322-539801943592',
                'categories' => ['kham-pha', 'nghi-duong'],
                'services' => ['Xe đưa đón', 'Hướng dẫn viên', 'Khách sạn 4 sao', 'Ăn sáng', 'Bảo hiểm du lịch'],
                'lat' => 12.2388,
                'lng' => 109.1967,
                'schedules' => [
                    ['cach' => 35, 'gio' => '19:00', 'max' => 30, 'so_hdv' => 2],
                    ['cach' => 90, 'gio' => '19:00', 'max' => 30],
                    ['cach' => 125, 'gio' => '19:00', 'max' => 30],
                ],
            ],

            // ── Tour HẾT CHỖ. ───────────────────────────────────────────────────────────────
            [
                'slug' => 'tour-moc-chau-2n1d',
                'title' => 'Tour Mộc Châu mùa hoa mận 2N1Đ',
                'description' => 'Thung lũng mận Nà Ka, đồi chè trái tim và thác Dải Yếm đúng mùa hoa.',
                'adult_price' => 1690000,
                'days' => 2,
                'from' => 'Hà Nội',
                'to' => 'Mộc Châu',
                'vehicle' => 'Xe 29 chỗ',
                'pickup' => 'Bến xe Mỹ Đình - Cổng chính',
                'thumbnail' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b',
                'status' => 'full',
                'categories' => ['kham-pha'],
                'services' => ['Xe đưa đón', 'Hướng dẫn viên'],
                'lat' => 20.8333,
                'lng' => 104.6333,
                'schedules' => [
                    // Đóng bán vì đã kín chỗ, không phải vì quá hạn chốt.
                    ['cach' => 11, 'gio' => '06:00', 'max' => 29, 'trang_thai' => ScheduleStatus::Closed],
                ],
            ],

            // ── Tour NGỪNG BÁN: còn lịch sử chuyến cũ, không mở chuyến mới. ─────────────────
            [
                'slug' => 'tour-cat-ba-2n1d',
                'title' => 'Tour Cát Bà 2N1Đ',
                'description' => 'Vịnh Lan Hạ và làng chài Việt Hải. Tạm ngừng bán để làm lại chương trình.',
                'adult_price' => 2290000,
                'days' => 2,
                'from' => 'Hà Nội',
                'to' => 'Cát Bà',
                'vehicle' => 'Xe 29 chỗ + phà',
                'pickup' => 'Nhà hát Lớn Hà Nội - Số 1 Tràng Tiền',
                'thumbnail' => 'https://images.unsplash.com/photo-1528127269322-539801943592',
                'status' => 'inactive',
                'categories' => ['bien-dao'],
                'services' => ['Xe đưa đón', 'Hướng dẫn viên'],
                'lat' => 20.7280,
                'lng' => 107.0480,
                'schedules' => [
                    ['cach' => -90, 'gio' => '06:00', 'max' => 25, 'trang_thai' => ScheduleStatus::Completed],
                ],
            ],

            // ── Tour trong THÙNG RÁC: để thử khôi phục và để kiểm tra nó biến khỏi mọi danh sách.
            [
                'slug' => 'tour-bien-hai-tien-2n1d',
                'title' => 'Tour biển Hải Tiến 2N1Đ',
                'description' => 'Chương trình cũ đã ngừng khai thác, giữ lại trong thùng rác để đối chiếu đơn cũ.',
                'adult_price' => 1490000,
                'days' => 2,
                'from' => 'Hà Nội',
                'to' => 'Thanh Hóa',
                'vehicle' => 'Xe 45 chỗ',
                'pickup' => 'Bến xe Giáp Bát',
                'thumbnail' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e',
                'deleted' => true,
                'categories' => ['bien-dao'],
                'services' => ['Xe đưa đón'],
                'lat' => 19.8066,
                'lng' => 105.7852,
                'schedules' => [
                    ['cach' => -120, 'gio' => '06:00', 'max' => 45, 'trang_thai' => ScheduleStatus::Completed],
                ],
            ],
        ];
    }
}
