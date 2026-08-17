<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PassengerPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
}
