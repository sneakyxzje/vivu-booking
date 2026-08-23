<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TourSchedule;
use App\Services\PassengerPolicyService;
use App\Support\GioVietNam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * G03, G05 - Điều hành sửa danh sách hành khách sau hạn chốt, và xem đơn nào khai còn thiếu.
 *
 * Điều hành sửa được cả sau hạn chốt vì họ là người gọi cho nhà cung cấp báo đổi tên. Nhưng sau
 * khi đoàn lên đường thì cũng dừng: danh sách lúc đó là dữ liệu đang dùng để điểm danh.
 */
class AdminPassengerController extends Controller
{
    public function __construct(
        private PassengerPolicyService $passengerPolicy,
    ) {
    }

    public function index(int $bookingId): JsonResponse
    {
        $booking = Booking::query()->with('schedule')->find($bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $quyen = $this->passengerPolicy->editability($booking);

        return $this->success([
            'passengers' => $booking->passengers()->get(),
            'guests' => (int) $booking->guests,
            'can_edit' => $quyen['admin'],
            'locked_reason' => $quyen['admin'] ? null : $quyen['reason'],
            'warnings' => $this->passengerPolicy->manifestWarnings($booking),
        ], 'Lấy danh sách hành khách thành công');
    }

    public function update(Request $request, int $bookingId): JsonResponse
    {
        $validated = $request->validate(PassengerPolicyService::validationRules());

        $booking = Booking::query()->with('schedule')->find($bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $this->passengerPolicy->assertAdminCanEdit($booking);
        $this->passengerPolicy->validateList($booking, $validated['passengers']);

        DB::transaction(function () use ($booking, $validated) {
            $this->passengerPolicy->replaceList($booking, $validated['passengers']);
        });

        return $this->success([
            'passengers' => $booking->passengers()->get(),
            'warnings' => $this->passengerPolicy->manifestWarnings($booking->fresh()),
        ], 'Đã cập nhật danh sách hành khách.');
    }

    /**
     * G05 - Danh sách đoàn của một chuyến, chia theo từng nhóm.
     *
     * Một đơn là một nhóm: thường có một người đứng ra đăng ký cho cả nhà hoặc cả phòng ban, rồi
     * mới khai tên từng người. Nên câu hỏi của điều hành có hai tầng - đoàn này gồm những nhóm
     * nào, và nhóm đó cụ thể là ai - mà cả hai đều phải trả lời được ở cùng một chỗ.
     *
     * Trả về mọi nhóm chứ không riêng nhóm khai thiếu. Lọc sẵn ở máy chủ thì màn hình chỉ xem
     * được đúng nhóm có vấn đề, còn muốn tra một khách cụ thể lại phải mở từng đơn ra đếm - đúng
     * việc mà màn hình này sinh ra để khỏi phải làm.
     */
    public function manifest(int $scheduleId): JsonResponse
    {
        $bookings = Booking::query()
            ->where('tour_schedule_id', $scheduleId)
            ->whereNotIn('status', ['cancelled', 'transferred'])
            ->with('passengers')
            ->orderBy('id')
            ->get(['id', 'customer_name', 'customer_phone', 'customer_email', 'guests', 'status']);

        $nhom = $bookings->map(function (Booking $booking) {
            return [
                'booking_id' => $booking->id,
                // Người đứng ra đặt cho cả nhóm, không nhất thiết là người đi.
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'customer_email' => $booking->customer_email,
                'status' => $booking->status,
                'guests' => (int) $booking->guests,
                'declared' => $booking->passengers->count(),
                'missing' => $this->passengerPolicy->missingCount($booking),
                'warnings' => $this->passengerPolicy->manifestWarnings($booking),
                'passengers' => $booking->passengers->map(fn ($khach) => [
                    'id' => $khach->id,
                    'name' => $khach->name,
                    'type' => $khach->type,
                    'gender' => $khach->gender,
                    'date_of_birth' => $khach->date_of_birth?->toDateString(),
                    'identity_number' => $khach->identity_number,
                    'id_type' => $khach->id_type,
                    'nationality' => $khach->nationality,
                    'phone' => $khach->phone,
                    // Ăn chay, dị ứng, cần hỗ trợ di chuyển: thứ nhà cung cấp phải biết trước.
                    'special_request' => $khach->special_request,
                    'is_contact' => (bool) $khach->is_contact,
                    'note' => $khach->note,
                ])->values(),
            ];
        })->values();

        $conThieu = $nhom->sum('missing');

        return $this->success([
            'groups' => $nhom,
            'total_groups' => $nhom->count(),
            'total_guests' => $nhom->sum('guests'),
            'total_declared' => $nhom->sum('declared'),
            'total_missing' => $conThieu,
            // Danh sách đoàn chỉ gửi nhà cung cấp được khi mọi nhóm đã khai đủ người.
            'can_export_manifest' => $conThieu === 0
                && $nhom->every(fn (array $row) => $row['warnings'] === []),
        ], 'Lấy danh sách đoàn thành công');
    }

    /**
     * Xuất danh sách đoàn thành tệp gửi được cho nhà cung cấp.
     *
     * Đây là thứ khách sạn và nhà xe đòi trước khi nhận đoàn, và là thứ hướng dẫn viên in ra cầm
     * theo. Màn hình đã hiển thị đủ dữ liệu từ lâu, chỉ chưa có đường đưa nó ra khỏi màn hình.
     *
     * **CSV chứ không phải .xlsx**, và đó là lựa chọn có chủ ý: Excel mở CSV như một bảng tính
     * bình thường, còn sinh .xlsx thật thì phải thêm thư viện chỉ để làm đúng một việc này.
     *
     * Hai chi tiết nhỏ quyết định tệp có đọc được ở Việt Nam hay không:
     *
     *   - **BOM UTF-8 ở đầu tệp.** Thiếu nó, Excel trên Windows đọc theo bảng mã hệ thống và mọi
     *     dấu tiếng Việt thành ký tự lạ. Ba byte này là toàn bộ khác biệt giữa một tệp dùng được
     *     và một tệp phải gõ lại bằng tay.
     *   - **Dấu chấm phẩy làm dấu phân cách.** Excel bản tiếng Việt hiểu dấu phẩy là dấu thập
     *     phân, nên tệp ngăn bằng dấu phẩy dồn hết vào một cột.
     *
     * Cố ý KHÔNG chặn khi danh sách còn khai thiếu. Điều hành vẫn cần bản nháp để đối chiếu và để
     * biết còn thiếu ai; cột "Ghi chú" nói thẳng dòng nào chưa khai. `can_export_manifest` vẫn ở
     * đó cho màn hình cảnh báo trước khi gửi ra ngoài.
     */
    public function exportManifest(int $scheduleId): StreamedResponse
    {
        $schedule = TourSchedule::query()->with('tour:id,title')->findOrFail($scheduleId);

        $bookings = Booking::query()
            ->where('tour_schedule_id', $scheduleId)
            ->whereNotIn('status', ['cancelled', 'transferred'])
            ->with('passengers')
            ->orderBy('id')
            ->get();

        $tenTep = sprintf(
            'danh-sach-doan-chuyen-%d-%s.csv',
            $scheduleId,
            GioVietNam::bayGio()->format('Ymd-Hi'),
        );

        return response()->streamDownload(function () use ($schedule, $bookings) {
            $tep = fopen('php://output', 'wb');

            // BOM UTF-8. Xem khối chú thích ở trên - bỏ dòng này là hỏng cả tệp trên máy Windows.
            fwrite($tep, "\xEF\xBB\xBF");

            $ghi = static fn (array $dong) => fputcsv($tep, $dong, ';', '"', '\\');

            $ghi(['DANH SÁCH ĐOÀN']);
            $ghi(['Tour', $schedule->tour?->title]);
            $ghi(['Mã chuyến', '#' . $schedule->id]);
            $ghi(['Khởi hành', $schedule->start_date?->format('d/m/Y H:i')]);
            $ghi(['Xuất lúc', GioVietNam::bayGio()->format('d/m/Y H:i')]);
            $ghi([]);

            $ghi([
                'STT', 'Mã đơn', 'Người đặt', 'Điện thoại người đặt',
                'Họ và tên khách', 'Loại khách', 'Giới tính', 'Ngày sinh',
                'Loại giấy tờ', 'Số giấy tờ', 'Quốc tịch', 'Điện thoại',
                'Yêu cầu đặc biệt', 'Ghi chú',
            ]);

            $stt = 0;

            foreach ($bookings as $booking) {
                if ($booking->passengers->isEmpty()) {
                    // Nhóm chưa khai vẫn phải có mặt trong tệp, kèm lý do - nếu bỏ qua thì tổng
                    // số khách trên tệp ít hơn số chỗ đã bán mà không ai giải thích được vì sao.
                    $ghi([
                        ++$stt,
                        'BK-' . $booking->id,
                        $booking->customer_name,
                        $booking->customer_phone,
                        '', '', '', '', '', '', '', '', '',
                        sprintf('CHƯA KHAI - còn thiếu %d khách', (int) $booking->guests),
                    ]);

                    continue;
                }

                foreach ($booking->passengers as $khach) {
                    $ghi([
                        ++$stt,
                        'BK-' . $booking->id,
                        $booking->customer_name,
                        $booking->customer_phone,
                        $khach->name,
                        self::NHAN_LOAI_KHACH[$khach->type] ?? $khach->type,
                        self::NHAN_GIOI_TINH[$khach->gender] ?? '',
                        $khach->date_of_birth?->format('d/m/Y'),
                        $khach->id_type ? mb_strtoupper($khach->id_type) : '',
                        $khach->identity_number,
                        $khach->nationality,
                        $khach->phone,
                        $khach->special_request,
                        $khach->is_contact ? 'Người liên hệ của nhóm' : ($khach->note ?? ''),
                    ]);
                }
            }

            $ghi([]);
            $ghi(['Tổng số khách đã khai', $stt]);
            $ghi(['Tổng số chỗ đã bán', $bookings->sum('guests')]);

            fclose($tep);
        }, $tenTep, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private const NHAN_LOAI_KHACH = [
        'adult' => 'Người lớn',
        'child' => 'Trẻ em',
        'infant' => 'Em bé',
    ];

    private const NHAN_GIOI_TINH = [
        'male' => 'Nam',
        'female' => 'Nữ',
        'other' => 'Khác',
    ];
}
