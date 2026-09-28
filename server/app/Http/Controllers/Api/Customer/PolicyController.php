<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Services\BookingTransferService;
use App\Services\CancellationPolicyService;
use Illuminate\Http\JsonResponse;

/** Chính sách công khai dùng cùng mốc hạn chốt với phép tính hoàn tiền. */
class PolicyController extends Controller
{
    public function show(): JsonResponse
    {
        return $this->success([
            'cancellation' => [
                'name' => CancellationPolicyService::NAME,
                'version' => CancellationPolicyService::VERSION,
                'description' => 'Trước hạn chốt danh sách: hoàn đủ số đã thanh toán. Từ hạn chốt đến trước khởi hành: giữ cọc 50% giá trị đơn, hoàn phần đã trả vượt cọc. Đã khởi hành: không cho hủy.',
                'effective_from' => null,
                'rules' => CancellationPolicyService::publicRules(),
            ],
            'transfer' => [
                'notice_days' => max(0, (int) config('booking.transfer_notice_days', 0)),
                'free_transfers' => BookingTransferService::FREE_TRANSFERS,
                'fee' => (float) config('booking.transfer_fee', 200_000),
            ],
            'booking' => [
                'payment_ttl_minutes' => (int) config('booking.payment_ttl_minutes', 10),
                'deadline_days' => (int) config('booking.booking_deadline_days', 3),
            ],
            'payment' => [
                'deposit_percent' => 50,
                'balance_due_rule' => 'booking_deadline',
                'reminder_days' => (int) config('booking.balance_reminder_days', 7),
                'final_notice_days' => (int) config('booking.balance_final_notice_days', 2),
            ],
        ], 'Lấy chính sách thành công');
    }
}
