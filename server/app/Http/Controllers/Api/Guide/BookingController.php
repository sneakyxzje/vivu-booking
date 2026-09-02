<?php

namespace App\Http\Controllers\Api\Guide;

use App\Enums\BookingAuditAction;
use App\Http\Controllers\Controller;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Services\BookingAuditLogger;
use App\Services\BookingPaymentService;
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
        private BookingPaymentService $payments,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $guideId = $request->user()->id;

        $bookings = Booking::query()
            ->with(['tour:id,title', 'schedule:id,start_date'])
            ->whereHas('schedule', fn ($query) => $query
                ->whereHas('guides', fn ($q) => $q->whereKey($guideId)))
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
     * Đi qua đúng đường mà quản trị đi: khóa dòng, đọc lại, ghi nhật ký, **và ghi tiền vào sổ**.
     * Đây là thao tác khẳng định "khách này đã trả tiền", nên số tiền là bắt buộc: trước đây hàm
     * này chỉ đổi trạng thái, tức là người cầm tiền mặt của khách không phải khai đã cầm bao nhiêu,
     * và sổ giao dịch vẫn ghi đơn ấy thu 0 đồng.
     */
    public function confirm(Request $request, int $id): JsonResponse
    {
        $booking = Booking::query()
            ->whereHas('schedule', fn ($query) => $query
                ->whereHas('guides', fn ($q) => $q->whereKey($request->user()->id)))
            ->find($id);

        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đặt chỗ thuộc chuyến được phân công',
            ], 404);
        }

        // Bắt nhập tiền, trừ khi sổ đã ghi khoản thu từ trước - khách chuyển khoản cho văn phòng
        // rồi mới ra điểm tập trung là chuyện thường. Xem chú thích ở AdminBookingController.
        $batBuoc = $this->payments->netPaid($booking) > 0 ? 'nullable' : 'required';

        $data = $request->validate([
            'amount' => [$batBuoc, 'numeric', 'min:1'],
            'method' => [$batBuoc, 'in:cash,bank_transfer'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.required' => 'Nhập số tiền vừa thu của khách.',
            'method.required' => 'Chọn hình thức thu: tiền mặt hay chuyển khoản.',
        ]);

        $daXacNhan = DB::transaction(function () use ($booking, $data, $request) {
            // Đọc lại sau khi khóa: quản trị có thể vừa xác nhận hoặc vừa hủy chính đơn này,
            // và tác vụ nhả chỗ quá hạn cũng có thể vừa chạm tới nó.
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            if (!$locked || $locked->status !== 'pending') {
                return false;
            }

            if (!empty($data['amount'])) {
                $this->payments->recordManualCollection(
                    $locked,
                    (float) $data['amount'],
                    $data['method'],
                    null,
                    $data['note'] ?? 'Hướng dẫn viên thu tại điểm tập trung',
                    $request->user(),
                );
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
                empty($data['amount'])
                    ? ['collected_earlier' => true]
                    : ['amount' => round((float) $data['amount']), 'method' => $data['method']],
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