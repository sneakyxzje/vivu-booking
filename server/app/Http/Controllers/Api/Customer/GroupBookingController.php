<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\GroupBookingRequest;
use App\Services\GroupBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phía khách của luồng booking đoàn: gửi yêu cầu, theo dõi bằng mã tra cứu, rút yêu cầu.
 *
 * Không cần tài khoản - cùng triết lý với đơn lẻ: người đại diện đoàn thường là nhân viên hành
 * chính đặt hộ cả công ty, bắt họ đăng ký tài khoản chỉ để hỏi giá là dựng rào chắn trước cửa.
 * Mã tra cứu ngẫu nhiên là chìa khóa; ai giữ mã, người đó xem được.
 */
class GroupBookingController extends Controller
{
    public function __construct(private GroupBookingService $service)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tour_id' => ['required', 'integer', 'exists:tours,id'],
            'tour_schedule_id' => ['required', 'integer', 'exists:tour_schedules,id'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'estimated_guests' => ['required', 'integer', 'min:5', 'max:500'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:20'],
            'invoice_address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'estimated_guests.min' => 'Đoàn từ 5 người trở lên. Ít hơn thì đặt theo form khách lẻ - nhanh hơn và thấy giá ngay.',
        ]);

        $user = auth('sanctum')->user();

        $yeuCau = $this->service->submit($data, $user?->role === 'customer' ? $user : null);

        return $this->success([
            'public_token' => $yeuCau->public_token,
            'status' => $yeuCau->status->value,
        ], 'Đã nhận yêu cầu. Điều hành sẽ liên hệ báo giá qua số điện thoại bạn để lại. Giữ mã tra cứu để theo dõi.', 201);
    }

    public function show(string $publicToken): JsonResponse
    {
        $yeuCau = GroupBookingRequest::query()
            ->where('public_token', $publicToken)
            ->with(['tour:id,title,slug', 'schedule:id,start_date,booking_deadline', 'booking:id,group_booking_request_id,public_token,guests,total_amount,status,paid_at'])
            ->first();

        if (!$yeuCau) {
            return $this->error('Không tìm thấy yêu cầu với mã này.', 404);
        }

        return $this->success([
            'public_token' => $yeuCau->public_token,
            'status' => $yeuCau->status->value,
            'status_label' => $yeuCau->status->label(),
            'tour_title' => $yeuCau->tour?->title,
            'start_date' => $yeuCau->schedule?->start_date,
            'contact_name' => $yeuCau->contact_name,
            'estimated_guests' => $yeuCau->estimated_guests,
            'quote' => $yeuCau->quoted_price_per_person === null ? null : [
                'price_per_person' => (float) $yeuCau->quoted_price_per_person,
                'free_slots' => $yeuCau->quoted_free_slots,
                'note' => $yeuCau->quote_note,
                'expires_at' => $yeuCau->quote_expires_at,
                'expired' => $yeuCau->quoteExpired(),
            ],
            'rejected_reason' => $yeuCau->rejected_reason,
            // Đã chốt thì trỏ sang đơn thật - mọi theo dõi tiếp theo nằm ở màn tra cứu đơn.
            'booking' => $yeuCau->booking === null ? null : [
                'public_token' => $yeuCau->booking->public_token,
                'guests' => $yeuCau->booking->guests,
                'total_amount' => (float) $yeuCau->booking->total_amount,
                'status' => $yeuCau->booking->status,
                'paid_in_full' => $yeuCau->booking->paid_at !== null,
            ],
        ], 'Lấy thông tin yêu cầu thành công');
    }

    public function withdraw(string $publicToken): JsonResponse
    {
        $yeuCau = GroupBookingRequest::query()->where('public_token', $publicToken)->first();

        if (!$yeuCau) {
            return $this->error('Không tìm thấy yêu cầu với mã này.', 404);
        }

        $this->service->withdraw($yeuCau);

        return $this->success(null, 'Đã rút yêu cầu. Bạn có thể gửi yêu cầu mới bất cứ lúc nào.');
    }
}
