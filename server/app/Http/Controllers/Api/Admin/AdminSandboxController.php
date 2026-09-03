<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Services\BookingMailDispatcher;
use App\Services\SandboxDemoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bảng điều khiển sân thử nghiệm nghiệp vụ.
 *
 * Ba nhóm việc, và ranh giới giữa chúng là điều quan trọng nhất ở đây:
 *
 *   - **Tua thời gian** — chỉ trong tour có cờ sandbox. Đây là quyền nguy hiểm: dời ngày khởi hành
 *     của chuyến đã có khách là dời hạn thanh toán của từng đơn.
 *   - **Chạy lệnh nền** — chạy đúng lệnh mà máy chủ hẹn giờ chạy hằng đêm, không phải bản rút gọn.
 *     Lệnh tự lọc theo điều kiện của nó nên không cần khóa theo tour, nhưng vẫn chỉ quản trị gọi được.
 *   - **Gửi lại thư** — dùng cho MỌI đơn, kể cả tour thật: người vận hành thường xuyên cần gửi lại
 *     một lá thư cho khách gọi lên nói chưa nhận được.
 */
class AdminSandboxController extends Controller
{
    public function __construct(
        private readonly SandboxDemoService $sandbox,
        private readonly BookingMailDispatcher $mailer,
    ) {
    }

    /** Danh mục kịch bản nghiệp vụ, gom theo nhóm. */
    public function scenarios(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => \App\Services\Sandbox\SandboxScenarioRunner::danhMuc(),
        ]);
    }

    /**
     * Chạy một kịch bản và trả về biên bản từng bước.
     *
     * Kịch bản tự dựng dữ liệu của nó và tự chấm từng bước, nên phản hồi này là thứ duy nhất giao
     * diện cần — không phải gọi thêm ảnh chụp hay đối chiếu gì.
     */
    public function runScenario(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'string']]);

        $bienBan = app(\App\Services\Sandbox\SandboxScenarioRunner::class)->chay($data['id']);

        return response()->json([
            'success' => true,
            'message' => $bienBan['dat']
                ? 'Kịch bản chạy đúng ở mọi bước.'
                : 'Có bước KHÔNG đạt — xem biên bản bên dưới.',
            'data' => $bienBan,
        ]);
    }

    /** Danh sách mốc, lệnh và loại thư — để giao diện dựng nút mà không gõ lại chuỗi nào. */
    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'milestones' => SandboxDemoService::MOC,
                'commands' => SandboxDemoService::LENH,
                'mails' => BookingMailDispatcher::danhSach(),
            ],
        ]);
    }

    /** Ảnh chụp mọi đơn của một chuyến: dùng để so trước và sau khi bấm. */
    public function snapshot(int $scheduleId): JsonResponse
    {
        $schedule = TourSchedule::query()->with('tour')->findOrFail($scheduleId);

        return response()->json([
            'success' => true,
            'data' => [
                'schedule' => [
                    'id' => $schedule->id,
                    'tour_title' => $schedule->tour?->title,
                    'is_sandbox' => (bool) $schedule->tour?->is_sandbox,
                    'start_date' => $schedule->start_date?->format('d/m/Y H:i'),
                    'booking_deadline' => $schedule->booking_deadline?->format('d/m/Y H:i'),
                    'status' => $schedule->status,
                ],
                'bookings' => $this->sandbox->anhChup($schedule),
            ],
        ]);
    }

    /**
     * Tua chuyến tới một mốc, rồi trả về ảnh chụp mới.
     *
     * Trả kèm ảnh chụp ngay trong cùng phản hồi: người bấm cần thấy hậu quả tức thì, không phải bấm
     * thêm một lần nữa để tải lại bảng.
     */
    public function fastForward(Request $request, int $scheduleId): JsonResponse
    {
        $data = $request->validate([
            'milestone' => ['required', 'string', 'in:' . implode(',', array_keys(SandboxDemoService::MOC))],
        ]);

        $schedule = TourSchedule::query()->with('tour')->findOrFail($scheduleId);

        $ketQua = $this->sandbox->tuaToiMoc($schedule, $data['milestone']);

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Đã dời ngày khởi hành từ %s sang %s (%s). Bây giờ: %s.',
                $ketQua['khoi_hanh_cu'],
                $ketQua['khoi_hanh_moi'],
                $ketQua['so_ngay_da_doi'] >= 0
                    ? '+' . $ketQua['so_ngay_da_doi'] . ' ngày'
                    : $ketQua['so_ngay_da_doi'] . ' ngày',
                $ketQua['moc'],
            ),
            'data' => [
                'fast_forward' => $ketQua,
                'bookings' => $this->sandbox->anhChup($schedule->fresh(['tour'])),
            ],
        ]);
    }

    /** Chạy một lệnh nền và trả về nguyên văn đầu ra của nó. */
    public function runCommand(Request $request): JsonResponse
    {
        $data = $request->validate([
            'command' => ['required', 'string', 'in:' . implode(',', array_keys(SandboxDemoService::LENH))],
            'schedule_id' => ['nullable', 'integer'],
        ]);

        $ketQua = $this->sandbox->chayLenh($data['command']);

        $anhChup = null;

        if (!empty($data['schedule_id'])) {
            $schedule = TourSchedule::query()->with('tour')->find($data['schedule_id']);
            $anhChup = $schedule ? $this->sandbox->anhChup($schedule) : null;
        }

        return response()->json([
            'success' => true,
            'message' => $ketQua['mo_ta'] . ' — đã chạy xong.',
            'data' => [
                'command' => $ketQua,
                'bookings' => $anhChup,
            ],
        ]);
    }

    /** Ghép chuyến nguồn vào chuyến đích, bằng chính dịch vụ ghép thật. */
    public function merge(Request $request, int $scheduleId): JsonResponse
    {
        $data = $request->validate([
            'target_schedule_id' => ['required', 'integer', 'different:' . $scheduleId],
        ]);

        $nguon = TourSchedule::query()->with('tour')->findOrFail($scheduleId);
        $dich = TourSchedule::query()->with('tour')->findOrFail($data['target_schedule_id']);

        $ketQua = $this->sandbox->ghepChuyen($nguon, $dich, $request->user());

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Đã ghép chuyến #%d vào #%d: %d đơn được chuyển, %d đơn chưa cọc bị hủy và mời đặt lại. '
                    . 'Chuyến nguồn đã đóng.',
                $nguon->id,
                $dich->id,
                $ketQua['transferred'],
                $ketQua['cancelled'],
            ),
            'data' => [
                'merge' => $ketQua,
                // Bảng của chuyến ĐÍCH: khách vừa dồn về đây, và hạn trả nốt của họ vừa đổi theo.
                'bookings' => $this->sandbox->anhChup($dich->fresh(['tour'])),
            ],
        ]);
    }

    /**
     * Chuyển một đơn sang chuyến khác.
     *
     * `initiated_by` là thứ đáng bấm cả hai chiều: cùng thao tác, hai kết cục — công ty dời ngày thì
     * miễn phí và hoàn đủ nếu hủy sau đó, khách tự xin đổi thì chịu phí và chịu bảng phí hủy.
     */
    public function transfer(Request $request, int $bookingId): JsonResponse
    {
        $data = $request->validate([
            'target_schedule_id' => ['required', 'integer'],
            'initiated_by' => ['required', 'string', 'in:customer,company'],
        ]);

        $booking = Booking::query()->with('tour')->findOrFail($bookingId);
        $dich = TourSchedule::query()->with('tour')->findOrFail($data['target_schedule_id']);

        $banGhi = $this->sandbox->chuyenChuyen(
            $booking,
            $dich,
            $data['initiated_by'],
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Đã chuyển đơn #%d sang chuyến #%d (%s). Chênh giá %s đ, phí đổi lịch %s đ.',
                $booking->id,
                $dich->id,
                $data['initiated_by'] === 'company' ? 'công ty dời ngày' : 'khách xin đổi',
                number_format((float) $banGhi->price_difference, 0, ',', '.'),
                number_format((float) $banGhi->fee, 0, ',', '.'),
            ),
            'data' => [
                'bookings' => $this->sandbox->anhChup($dich->fresh(['tour'])),
            ],
        ]);
    }

    /** Gửi lại một lá thư của đơn, ngay lập tức. */
    public function sendMail(Request $request, int $bookingId): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', array_keys(BookingMailDispatcher::danhSach()))],
        ]);

        $booking = Booking::query()->with(['tour', 'schedule', 'customer'])->findOrFail($bookingId);

        $ketQua = $this->mailer->gui($booking, $data['type']);

        return response()->json([
            'success' => true,
            'message' => sprintf('Đã gửi "%s" tới %s.', $ketQua['mo_ta'], $ketQua['gui_toi']),
            'data' => $ketQua,
        ]);
    }

    /** Các tour đang được đánh dấu là sân thử, kèm chuyến của chúng. */
    public function tours(): JsonResponse
    {
        $tours = Tour::query()
            ->where('is_sandbox', true)
            ->with(['schedules' => fn ($q) => $q->orderBy('start_date')])
            ->orderBy('id')
            ->get()
            ->map(fn (Tour $tour) => [
                'id' => $tour->id,
                'title' => $tour->title,
                'slug' => $tour->slug,
                'schedules' => $tour->schedules->map(fn (TourSchedule $s) => [
                    'id' => $s->id,
                    'start_date' => $s->start_date?->format('d/m/Y H:i'),
                    'booking_deadline' => $s->booking_deadline?->format('d/m/Y H:i'),
                    'status' => $s->status,
                    'booked_people' => (int) $s->booked_people,
                    'max_people' => (int) $s->max_people,
                ])->values(),
            ]);

        return response()->json(['success' => true, 'data' => $tours]);
    }
}
