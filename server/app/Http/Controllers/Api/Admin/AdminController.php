<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Http\Resources\TourResource;
use App\Http\Resources\UserResource;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Service;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\BookingPaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Khoảng ngày dài hơn số này thì biểu đồ gom theo THÁNG thay vì theo NGÀY.
     *
     * Hai tháng là chỗ một biểu đồ cột theo ngày còn đọc được. Quá đó thì các cột mảnh tới mức
     * không so được với nhau, và người xem thật ra đang hỏi một câu khác: xu hướng theo tháng.
     */
    private const NGUONG_GOM_THEO_THANG = 62;

    /**
     * Dữ liệu bảng điều khiển.
     *
     * ## Hai loại con số, và vì sao khoảng ngày chỉ chạm vào một loại
     *
     * Trang này trộn hai thứ khác hẳn nhau:
     *
     *   - **Hiện trạng**: bao nhiêu tour đang bán, bao nhiêu chuyến sắp khởi hành, tỉ lệ lấp đầy,
     *     tổng số khách hàng. Chúng trả lời "lúc này hệ thống đang thế nào".
     *   - **Trong kỳ**: bao nhiêu đơn được đặt, thu về bao nhiêu, điểm đến nào bán chạy. Chúng
     *     trả lời "khoảng thời gian ấy làm ăn ra sao".
     *
     * Bộ lọc ngày chỉ áp cho loại thứ hai. Lọc "số tour đang hoạt động" theo một khoảng ngày là
     * một câu hỏi không có nghĩa, và trả về một con số nhỏ hơn sẽ khiến người xem tưởng tour vừa
     * biến mất. Giao diện đánh dấu rõ nhóm nào đi theo bộ lọc.
     *
     * ## Không truyền khoảng thì giữ nguyên hành vi cũ
     *
     * Thiếu `from`/`to` thì tổng vẫn là toàn thời gian và biểu đồ vẫn là mười hai tháng của năm
     * nay, đúng như trước khi có bộ lọc. Nhờ vậy thêm tính năng này không đổi thứ mà mọi màn hình
     * và bài kiểm thử đang trông đợi.
     */
    public function dashboardData(Request $request, BookingPaymentService $payments): JsonResponse
    {
        $loc = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], [
            'to.after_or_equal' => 'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.',
        ]);

        /*
         * Nới hai đầu ra trọn ngày.
         *
         * Ô chọn ngày gửi lên "2026-09-30", tức 00:00 của hôm đó. Dùng thẳng làm mốc trên thì mọi
         * giao dịch trong chính ngày 30 đều rơi ra ngoài, và người dùng thấy một ngày bị mất mà
         * không hiểu vì sao.
         */
        $tu = isset($loc['from']) ? Carbon::parse($loc['from'])->startOfDay() : null;
        $den = isset($loc['to']) ? Carbon::parse($loc['to'])->endOfDay() : null;
        $coLoc = $tu !== null || $den !== null;

        /** Giới hạn một truy vấn vào khoảng đang lọc, theo cột thời gian của chính nó. */
        $trongKy = static function ($query, string $cot) use ($tu, $den) {
            if ($tu) {
                $query->where($cot, '>=', $tu);
            }

            if ($den) {
                $query->where($cot, '<=', $den);
            }

            return $query;
        };

        $summary = [
            'full_tours' => Tour::where('status', 'full')->count(),
            'active_tours' => Tour::where('status', 'active')->count(),
            'inactive_tours' => Tour::where('status', 'inactive')->count(),
            'total_tours' => Tour::count(),
            'featured_tours' => Tour::where('is_featured', true)->count(),
            'new_users_this_week' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'total_customers' => User::where('role', 'customer')->count(),
            'total_guides' => User::where('role', 'guide')->count(),
            'total_categories' => Category::count(),
            'inactive_categories' => Category::where('is_active', false)->count(),
            'total_services' => Service::count(),
            'upcoming_schedules' => TourSchedule::where('start_date', '>=', now()->toDateString())->count(),
            'total_booked_slots' => (int) TourSchedule::sum('booked_people'),
            'full_schedules' => TourSchedule::where('status', ScheduleStatus::Closed->value)->count(),
            'inactive_schedules' => TourSchedule::whereIn('status', [ScheduleStatus::Cancelled->value, ScheduleStatus::Completed->value])->count(),
        ];

        $topSellingTours = Tour::withSum('schedules as total_booked', 'booked_people')
            ->orderByDesc('total_booked')
            ->limit(5)
            ->get();

        if ($topSellingTours->every(fn ($tour) => (int) $tour->total_booked === 0)) {
            $topSellingTours = Tour::where('is_featured', true)
                ->latest()
                ->limit(5)
                ->get();
        }

        $recentFullTours = Tour::with('admin:id,name,email')
            ->where('status', 'full')
            ->latest()
            ->limit(5)
            ->get();

        $recentUsers = User::latest()->limit(5)->get();

        $topServices = Service::withCount('tours')
            ->orderByDesc('tours_count')
            ->limit(5)
            ->get(['id', 'name', 'icon']);

        $toursByCategory = Category::withCount('tours')
            ->orderByDesc('tours_count')
            ->get(['id', 'name', 'slug', 'is_active']);

        $featuredToursList = Tour::where('is_featured', true)
            ->latest()
            ->limit(5)
            ->get();

        /*
         * Mọi con số dưới đây tính bằng câu lệnh gộp, KHÔNG nạp bảng đơn hàng về bộ nhớ.
         *
         * Bản cũ kéo **toàn bộ** bảng `bookings` kèm quan hệ tour rồi đếm và cộng bằng PHP, với lý
         * do cho SQLite và MySQL chạy giống nhau. Lý do ấy đúng cho vài chục dòng dữ liệu mẫu và
         * sai dần theo thời gian: mỗi đơn mới là một đối tượng nữa phải dựng lên mỗi lần ai đó mở
         * bảng điều khiển. Đến vài chục nghìn đơn thì trang chậm rồi hết bộ nhớ — và nó hỏng đúng
         * lúc hệ thống đang chạy tốt nhất, tức lúc bán được nhiều nhất.
         *
         * Các phép đếm và cộng ở đây đều là SQL chuẩn, chạy như nhau trên cả hai hệ.
         */
        $demTheoTrangThai = $trongKy(
            Booking::query()->selectRaw('status, count(*) as so_luong'),
            'created_at',
        )
            ->groupBy('status')
            ->pluck('so_luong', 'status');

        $dem = static fn (string $trangThai): int => (int) ($demTheoTrangThai[$trangThai] ?? 0);

        // Doanh thu phải gom cả đơn đã chốt sau chuyến, không chỉ 'confirmed'. Từ D03, đơn của
        // chuyến đã đi xong chuyển sang 'completed' hoặc 'no_show'; lọc đúng 'confirmed' thì cứ
        // mỗi chuyến kết thúc doanh thu lại tụt xuống mà không ai hiểu tiền đi đâu.
        $trangThaiDoanhThu = BookingStatus::revenueValues();
        $totalCapacity = (int) TourSchedule::sum('max_people');

        $bookingSummary = [
            'total_bookings' => (int) $demTheoTrangThai->sum(),
            'pending_bookings' => $dem(BookingStatus::Pending->value),
            'confirmed_bookings' => $dem(BookingStatus::Confirmed->value),
            'completed_bookings' => $dem(BookingStatus::Completed->value),
            'no_show_bookings' => $dem(BookingStatus::NoShow->value),
            'cancelled_bookings' => $dem(BookingStatus::Cancelled->value),
            /*
             * Doanh thu là tiền ĐÃ VỀ, cộng từ sổ giao dịch.
             *
             * Trước đây hai dòng này cộng `total_amount`, tức giá trị đơn hàng. Một đơn vừa xác
             * nhận mà khách còn nợ vẫn cộng đủ, nên con số trên bảng điều khiển luôn cao hơn số dư
             * tài khoản thật và không đối chiếu được với sổ sách.
             */
            'total_revenue' => $payments->sumCollectedBetween($tu, $den, $trangThaiDoanhThu),
            /*
             * Gom theo NGÀY TIỀN VỀ, không theo ngày đơn được tạo.
             *
             * Cách cũ lọc đơn có `created_at` trong tháng rồi cộng số đã thu của chúng — nên một
             * đơn đặt cuối tháng trước mà trả tiền đầu tháng này được tính vào tháng trước. Con số
             * ấy không đối chiếu được với sao kê ngân hàng, mà đối chiếu sao kê là việc duy nhất
             * người ta dùng nó. Với đơn đoàn trả nhiều đợt thì độ lệch còn lớn hơn.
             */
            'revenue_this_month' => $payments->sumCollectedBetween(
                now()->startOfMonth(),
                now(),
                $trangThaiDoanhThu,
            ),
            // Tổng giá trị đơn đã bán, tách riêng: nó trả lời câu "bán được bao nhiêu", khác hẳn
            // câu "thu về bao nhiêu" ở trên.
            'contracted_value' => (float) $trongKy(
                Booking::query()->whereIn('status', $trangThaiDoanhThu),
                'created_at',
            )->sum('total_amount'),
            /*
             * Đang lọc thì đếm khách mới TRONG KHOẢNG; không lọc thì giữ nguyên "trong tháng này".
             *
             * Tên trường giữ nguyên để không phải sửa mọi chỗ đang đọc nó; nhãn trên giao diện đổi
             * theo việc có bộ lọc hay không.
             */
            'new_customers_this_month' => $coLoc
                ? $trongKy(User::where('role', 'customer'), 'created_at')->count()
                : User::where('role', 'customer')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count(),
            'occupancy_rate' => $totalCapacity > 0
                ? round($summary['total_booked_slots'] / $totalCapacity * 100, 1)
                : 0.0,
        ];

        $currentYear = now()->year;

        /*
         * Khung thời gian của biểu đồ.
         *
         * Không lọc thì vẫn là mười hai tháng của năm nay, y như trước. Có lọc thì chạy đúng khoảng
         * người dùng chọn, và **đổi đơn vị theo độ dài khoảng ấy**: khoảng ngắn vẽ theo ngày để
         * thấy được từng hôm, khoảng dài gom theo tháng vì cột theo ngày sẽ mảnh tới mức vô dụng.
         *
         * Thiếu một đầu thì lấy đầu còn lại làm mốc: chọn mỗi "từ ngày" nghĩa là từ đó tới hôm nay.
         */
        $mocDau = $coLoc
            ? ($tu ?? Booking::query()->min('created_at') ?? now()->startOfYear())
            : Carbon::create($currentYear, 1, 1)->startOfDay();
        $mocDau = Carbon::parse($mocDau)->startOfDay();

        $mocCuoi = $coLoc
            ? ($den ?? now())
            : Carbon::create($currentYear, 12, 31)->endOfDay();
        $mocCuoi = Carbon::parse($mocCuoi)->endOfDay();

        $theoNgay = $coLoc && $mocDau->diffInDays($mocCuoi) <= self::NGUONG_GOM_THEO_THANG;
        $donVi = $theoNgay ? 'day' : 'month';
        $nhieuNam = $mocDau->year !== $mocCuoi->year;

        /*
         * Số đơn đặt theo từng mốc — đếm bằng SQL, không lọc bằng PHP.
         *
         * Cắt mốc theo cách chạy được trên cả SQLite lẫn MySQL: mười ký tự đầu của `created_at` là
         * ngày, bảy ký tự đầu là tháng. `MONTH()` không có ở SQLite, `strftime()` không có ở MySQL.
         */
        $soKyTu = $theoNgay ? 10 : 7;

        $donTheoMoc = Booking::query()
            ->where('status', BookingStatus::Confirmed->value)
            ->where('created_at', '>=', $mocDau)
            ->where('created_at', '<=', $mocCuoi)
            ->selectRaw("substr(created_at, 1, {$soKyTu}) as moc, count(*) as so_luong")
            ->groupBy('moc')
            ->pluck('so_luong', 'moc');

        /*
         * Cột doanh thu gom theo mốc TIỀN VỀ; cột số đơn gom theo mốc ĐẶT.
         *
         * Hai trục cố ý khác nhau vì chúng trả lời hai câu khác nhau: "kỳ này thu được bao nhiêu"
         * và "kỳ này bán được mấy đơn". Trộn chúng vào một mốc thời gian thì một trong hai con số
         * sai, và biểu đồ không nói được câu nào cho ra câu nào.
         */
        $tienTheoMoc = $payments->sumCollectedGrouped($mocDau, $mocCuoi, $donVi, $trangThaiDoanhThu);

        $monthlyPerformance = collect();

        for (
            $moc = $mocDau->copy();
            $moc->lessThanOrEqualTo($mocCuoi);
            $theoNgay ? $moc->addDay() : $moc->addMonthNoOverflow()
        ) {
            $khoa = $moc->format($theoNgay ? 'Y-m-d' : 'Y-m');

            $monthlyPerformance->push([
                'name' => $theoNgay
                    ? $moc->format('d/m')
                    : ('T' . $moc->month . ($nhieuNam ? '/' . $moc->format('y') : '')),
                'revenue' => round(((float) ($tienTheoMoc[$khoa] ?? 0)) / 1_000_000, 1),
                'bookings' => (int) ($donTheoMoc[$khoa] ?? 0),
            ]);
        }

        $monthlyPerformance = $monthlyPerformance->values();

        // Điểm khởi hành được đặt nhiều nhất — gộp ở cơ sở dữ liệu, chỉ mang về sáu dòng.
        $destinations = $trongKy(
            Booking::query()
                ->join('tours', 'tours.id', '=', 'bookings.tour_id')
                ->where('bookings.status', BookingStatus::Confirmed->value),
            'bookings.created_at',
        )
            ->selectRaw('coalesce(tours.start_location, ?) as name, sum(bookings.guests) as value', ['Khác'])
            ->groupBy('name')
            ->orderByDesc('value')
            ->limit(6)
            ->get()
            ->map(fn ($dong) => ['name' => $dong->name, 'value' => (int) $dong->value])
            ->values();

        $recentBookings = $trongKy(
            Booking::query()->with('tour:id,title'),
            'created_at',
        )
            ->latest('created_at')
            ->limit(6)
            ->get(['id', 'tour_id', 'customer_name', 'total_amount', 'status', 'created_at'])
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'customer' => $booking->customer_name,
                'tour' => $booking->tour?->title ?? 'Tour #' . $booking->tour_id,
                'price' => (float) $booking->total_amount,
                'status' => $booking->status,
                'date' => $booking->created_at?->toIso8601String(),
            ])
            ->values();

        return $this->success([
            /*
             * Trả lại chính khoảng đang áp dụng, để giao diện gắn nhãn cho đúng.
             *
             * Không tự nghĩ ra nhãn ở phía client được: máy chủ mới là nơi quyết định biểu đồ gom
             * theo ngày hay theo tháng, và nơi nới hai đầu ra trọn ngày.
             */
            'range' => [
                'from' => $tu?->format('Y-m-d'),
                'to' => $den?->format('Y-m-d'),
                'granularity' => $donVi,
                'filtered' => $coLoc,
            ],
            'summary' => $summary,
            'booking_summary' => $bookingSummary,
            'monthly_performance' => $monthlyPerformance,
            'destinations' => $destinations,
            'recent_bookings' => $recentBookings,
            'top_selling_tours' => TourResource::collection($topSellingTours),
            'recent_full_tours' => TourResource::collection($recentFullTours),
            'recent_users' => UserResource::collection($recentUsers),
            'top_services' => $topServices->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'icon' => $service->icon,
                'tours_count' => $service->tours_count,
            ])->values(),
            'tours_by_category' => $toursByCategory->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'is_active' => (bool) $category->is_active,
                'tours_count' => $category->tours_count,
            ])->values(),
            'featured_tours_list' => TourResource::collection($featuredToursList),
        ], 'Lấy dữ liệu dashboard thành công');
    }
}


