<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\TourSchedule;
use App\Services\BookingHoldService;
use App\Services\BookingPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AdminBookingController extends Controller
{
    public function __construct(
        private BookingHoldService $holdService,
        private BookingPolicyService $bookingPolicy,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::with(['tour:id,title', 'customer:id,name,email,phone', 'schedule:id,start_date'])
            ->latest()
            ->paginate(10);

        return $this->success($bookings, 'Lấy danh sách đơn đặt hàng thành công');
    }

    /**
     * C03 - Các đơn đã hủy nhưng chỗ chưa được trả về kho.
     *
     * Đây là ghế chết: chỗ trống về mặt vật lý nhưng chưa bán lại được vì phòng, ghế và suất ăn
     * đã chốt theo danh sách gửi nhà cung cấp. Điều hành nhìn màn hình này để quyết định có xin
     * thêm suất rồi mở bán lại hay chấp nhận lỗ.
     *
     * Xem docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 3.
     */
    public function heldSeats(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->withHeldSeats()
            ->with(['tour:id,title', 'schedule:id,start_date,booking_deadline,max_people,booked_people'])
            ->orderBy('cancelled_at')
            ->paginate(10);

        $tongCho = (int) Booking::query()->withHeldSeats()->sum('guests');

        return $this->success([
            'bookings' => $bookings,
            'total_held_seats' => $tongCho,
        ], 'Lấy danh sách chỗ chưa mở bán lại thành công');
    }

    /**
     * Mở lại chỗ của một đơn đã hủy sau hạn chốt, trả chỗ về kho để bán tiếp.
     */
    public function releaseHeldSeats(Request $request, int $id): JsonResponse
    {
        $booking = Booking::query()->find($id);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $daMoLai = $this->holdService->releaseHeldSeats($booking, $request->user()?->id);

        if (!$daMoLai) {
            return $this->error(
                'Đơn này không ở trạng thái mở lại chỗ được. Chỉ đơn đã hủy mà chỗ chưa trả về kho mới mở lại được.',
                400,
            );
        }

        return $this->success(
            $booking->fresh(['tour:id,title', 'schedule:id,start_date,max_people,booked_people']),
            'Đã mở lại chỗ để bán tiếp.',
        );
    }

    public function show(int $id): JsonResponse
    {
        $booking = Booking::with(['tour', 'customer', 'schedule', 'paymentLogs', 'passengers'])->find($id);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        return $this->success($booking, 'Lấy chi tiết đơn đặt hàng thành công');
    }

    /**
     * Admin xác nhận đơn đang chờ (ví dụ khách chuyển khoản tay / thanh toán tại quầy).
     * Chỗ đã được giữ từ lúc đặt nên chỉ cần chốt trạng thái và bỏ hạn tự hủy.
     */
    public function confirm(int $id): JsonResponse
    {
        $booking = DB::transaction(function () use ($id) {
            $booking = Booking::query()->lockForUpdate()->find($id);

            if (!$booking || $booking->status !== 'pending') {
                return null;
            }

            $booking->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'expires_at' => null,
            ]);

            return $booking;
        });

        if (!$booking) {
            return $this->error('Chỉ có thể xác nhận đơn đang ở trạng thái chờ (pending).', 400);
        }

        $this->sendConfirmedMailAfterResponse($booking);

        return $this->success(
            $booking->fresh(['tour', 'customer', 'schedule', 'paymentLogs']),
            'Đã xác nhận đơn đặt tour.'
        );
    }

    /**
     * Admin hủy đơn (pending hoặc confirmed) kèm lý do — trả lại chỗ và lượt mã giảm giá.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ], [
            'cancel_reason.required' => 'Vui lòng nhập lý do hủy đơn.',
        ]);

        $target = Booking::query()->find($id);

        if (!$target) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $booking = DB::transaction(function () use ($target, $validated, $request) {
            $schedule = $target->tour_schedule_id
                ? TourSchedule::query()
                    ->whereKey($target->tour_schedule_id)
                    ->lockForUpdate()
                    ->first()
                : null;

            $booking = Booking::query()->lockForUpdate()->find($target->id);

            if (!$booking) {
                return null;
            }

            // Kiểm tra trên bản ghi vừa khóa: chuyến đã khởi hành thì quản trị cũng không hủy được.
            $this->bookingPolicy->assertCancellable($booking, $schedule);

            if (!in_array($booking->status, ['pending', 'confirmed'], true)) {
                return null;
            }

            $booking->update([
                'status' => 'cancelled',
                'cancel_reason' => $validated['cancel_reason'],
                'cancel_type' => 'by_company',
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()?->id,
            ]);

            $this->holdService->releaseHold($booking, $schedule);

            return $booking;
        });

        if (!$booking) {
            return $this->error('Đơn này đã bị hủy trước đó.', 400);
        }

        $this->sendCancelledMailAfterResponse($booking);

        return $this->success(
            $booking->fresh(['tour', 'customer', 'schedule', 'paymentLogs']),
            'Đã hủy đơn đặt tour và trả lại chỗ.'
        );
    }

    private function sendConfirmedMailAfterResponse(Booking $booking): void
    {
        app()->terminating(function () use ($booking) {
            try {
                Mail::to($booking->customer_email)->send(new BookingConfirmedMail($booking->fresh(['tour', 'schedule'])));
            } catch (Throwable $exception) {
                Log::warning('Không gửi được email xác nhận đơn.', [
                    'booking_id' => $booking->id,
                    'customer_email' => $booking->customer_email,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    private function sendCancelledMailAfterResponse(Booking $booking): void
    {
        app()->terminating(function () use ($booking) {
            try {
                Mail::to($booking->customer_email)->send(new BookingCancelledMail($booking->fresh(['tour', 'schedule'])));
            } catch (Throwable $exception) {
                Log::warning('Không gửi được email thông báo hủy đơn.', [
                    'booking_id' => $booking->id,
                    'customer_email' => $booking->customer_email,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }
}
