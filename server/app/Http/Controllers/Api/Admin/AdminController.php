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
    public function dashboardData(Request $request, BookingPaymentService $payments): JsonResponse
    {
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
        $demTheoTrangThai = Booking::query()
            ->selectRaw('status, count(*) as so_luong')
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
            'total_revenue' => $payments->sumCollectedBetween(null, null, $trangThaiDoanhThu),
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
            'contracted_value' => (float) Booking::query()
                ->whereIn('status', $trangThaiDoanhThu)
                ->sum('total_amount'),
            'new_customers_this_month' => User::where('role', 'customer')
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
            'occupancy_rate' => $totalCapacity > 0
                ? round($summary['total_booked_slots'] / $totalCapacity * 100, 1)
                : 0.0,
        ];

        $currentYear = now()->year;

        /*
         * Số đơn đặt theo từng tháng trong năm — đếm bằng SQL, không lọc bằng PHP.
         *
         * Trích tháng theo cách chạy được trên cả SQLite lẫn MySQL: gom theo bảy ký tự đầu của
         * `created_at` ("2026-09"). `MONTH()` không có ở SQLite, còn `strftime()` không có ở MySQL.
         */
        $donTheoThang = Booking::query()
            ->where('status', BookingStatus::Confirmed->value)
            ->whereYear('created_at', $currentYear)
            ->selectRaw('substr(created_at, 1, 7) as thang, count(*) as so_luong')
            ->groupBy('thang')
            ->pluck('so_luong', 'thang');

        $monthlyPerformance = collect(range(1, 12))->map(function (int $month) use ($donTheoThang, $payments, $currentYear, $trangThaiDoanhThu) {
            $dauThang = Carbon::create($currentYear, $month, 1)->startOfMonth();
            $khoa = $dauThang->format('Y-m');

            return [
                'name' => 'T' . $month,
                /*
                 * Cột doanh thu gom theo tháng TIỀN VỀ; cột số đơn gom theo tháng ĐẶT.
                 *
                 * Hai trục cố ý khác nhau vì chúng trả lời hai câu khác nhau: "tháng này thu được
                 * bao nhiêu" và "tháng này bán được mấy đơn". Trộn chúng vào một mốc thời gian thì
                 * một trong hai con số sai, và biểu đồ không nói được câu nào cho ra câu nào.
                 */
                'revenue' => round(
                    $payments->sumCollectedBetween(
                        $dauThang,
                        $dauThang->copy()->endOfMonth(),
                        $trangThaiDoanhThu,
                    ) / 1_000_000,
                    1,
                ),
                'bookings' => (int) ($donTheoThang[$khoa] ?? 0),
            ];
        })->values();

        // Điểm khởi hành được đặt nhiều nhất — gộp ở cơ sở dữ liệu, chỉ mang về sáu dòng.
        $destinations = Booking::query()
            ->join('tours', 'tours.id', '=', 'bookings.tour_id')
            ->where('bookings.status', BookingStatus::Confirmed->value)
            ->selectRaw('coalesce(tours.start_location, ?) as name, sum(bookings.guests) as value', ['Khác'])
            ->groupBy('name')
            ->orderByDesc('value')
            ->limit(6)
            ->get()
            ->map(fn ($dong) => ['name' => $dong->name, 'value' => (int) $dong->value])
            ->values();

        $recentBookings = Booking::query()
            ->with('tour:id,title')
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


