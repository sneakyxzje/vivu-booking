<?php

namespace App\Http\Controllers\Api\Guide;

use App\Enums\BookingAuditAction;
use App\Http\Controllers\Controller;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Services\BookingAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BookingController extends Controller
{
    public function __construct(
        private BookingAuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $guideId = $request->user()->id;

        $bookings = Booking::query()
            ->with(['tour:id,title', 'schedule:id,start_date,guide_id'])
            ->whereHas('schedule', fn ($query) => $query->where('guide_id', $guideId))
            ->latest()
            ->get()
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'tour_id' => $booking->tour_id,
                'tour_schedule_id' => $booking->tour_schedule_id,
                'tour_title' => $booking->tour?->title ?? '',
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_email,
                'customer_phone' => $booking->customer_phone,
                'departure_date' => $booking->departure_date,
                'guests' => (int) $booking->guests,
                'total_amount' => (float) $booking->total_amount,
                'status' => $booking->status,
                'created_at' => $booking->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách đặt chỗ của các chuyến được phân công thành công',
            'data' => $bookings,
        ]);
    }

    /**
     * Hướng dẫn viên xác nhận một đơn tại điểm tập trung.
     *
     * Tình huống thật: khách hẹn trả tiền mặt lúc lên xe. Hướng dẫn viên thu tiền rồi vào đây
     * xác nhận, và chỗ thôi bị tính là giữ tạm.
     *
     * Đi qua đúng đường mà quản trị đi: khóa dòng, đọc lại, ghi nhật ký. Đây là thao tác khẳng
     * định "khách này đã trả tiền", tức là một quyết định về tiền, nên không được có đường ghi
     * nào lách qua nhật ký. Trước đây hàm này ghi thẳng và không để lại dấu vết nào.
     */
    public function confirm(Request $request, int $id): JsonResponse
    {
        $booking = Booking::query()
            ->whereHas('schedule', fn ($query) => $query->where('guide_id', $request->user()->id))
            ->find($id);

        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đặt chỗ thuộc chuyến được phân công',
            ], 404);
        }

        $daXacNhan = DB::transaction(function () use ($booking) {
            // Đọc lại sau khi khóa: quản trị có thể vừa xác nhận hoặc vừa hủy chính đơn này,
            // và tác vụ nhả chỗ quá hạn cũng có thể vừa chạm tới nó.
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            if (!$locked || $locked->status !== 'pending') {
                return false;
            }

            $locked->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'expires_at' => null,
            ]);

            $this->auditLogger->logStatusChange(
                $locked,
                BookingAuditAction::Confirmed,
                'pending',
                'confirmed',
                'Hướng dẫn viên xác nhận tại điểm tập trung.',
            );

            return true;
        });

        if (! $daXacNhan) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể xác nhận đặt chỗ đang chờ.',
            ], 400);
        }

        $booking->refresh();

        app()->terminating(function () use ($booking) {
            try {
                Mail::to($booking->customer_email)->send(new BookingConfirmedMail($booking->fresh(['tour', 'schedule'])));
            } catch (Throwable $exception) {
                Log::warning('Không gửi được email xác nhận đặt chỗ.', [
                    'booking_id' => $booking->id,
                    'customer_email' => $booking->customer_email,
                    'error' => $exception->getMessage(),
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Xác nhận đặt chỗ thành công',
            'data' => $booking->fresh(['tour', 'schedule']),
        ]);
    }
}