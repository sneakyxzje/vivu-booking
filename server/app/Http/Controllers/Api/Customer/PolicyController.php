<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyRule;
use App\Services\BookingTransferService;
use App\Services\CancellationPolicyService;
use Illuminate\Http\JsonResponse;

/**
 * Chính sách công ty, cho khách đọc trước khi đặt.
 *
 * ## Vì sao trang chính sách gọi API chứ không viết cứng
 *
 * Bảng phí hủy nằm trong cơ sở dữ liệu và điều hành sửa được. Chép nó thành chữ trong mã giao diện
 * thì có hai bản: bản khách đọc và bản hệ thống tính. Hai bản ấy giống nhau đúng tới lần sửa đầu
 * tiên, và từ đó trở đi trang chính sách hứa một đằng còn lúc hủy đơn trừ tiền một nẻo.
 *
 * Mấy con số ở phần hỏi đáp cũng vậy: hạn báo trước, số lần đổi miễn phí, phí đổi lịch, thời gian
 * giữ chỗ - tất cả đều là hằng số hoặc cấu hình có thật trong mã. Trả về từ đây để trang chỉ có
 * một nguồn.
 */
class PolicyController extends Controller
{
    public function show(): JsonResponse
    {
        $policy = CancellationPolicy::dangApDung();

        return $this->success([
            'cancellation' => $this->bangPhi($policy),
            'transfer' => [
                /*
                 * 0 nghĩa là không có hạn báo trước riêng: khách đổi được tới tận hạn chốt danh
                 * sách. Trang chính sách phải nói đúng điều đó thay vì bịa ra một con số ngày,
                 * nếu không khách đọc xong lại tưởng mình đã hết quyền đổi.
                 */
                'notice_days' => max(0, (int) config('booking.transfer_notice_days', 0)),
                'free_transfers' => BookingTransferService::FREE_TRANSFERS,
                'fee' => (float) config('booking.transfer_fee', 200_000),
            ],
            'booking' => [
                'payment_ttl_minutes' => (int) config('booking.payment_ttl_minutes', 10),
                'deadline_days' => (int) config('booking.booking_deadline_days', 3),
            ],
            /*
             * Điều kiện thanh toán — phần khách cần đọc TRƯỚC khi bấm đặt.
             *
             * Bán theo cọc nghĩa là có một cái hạn thứ hai sau lúc đặt, và quá hạn đó thì mất tiền.
             * Trang chính sách mà không nói ra thì khách chỉ biết tới nó qua lá thư cảnh báo — tức
             * là biết sau khi đã trả tiền, đúng lúc không lùi được nữa.
             *
             * Các con số đọc từ cấu hình, không viết cứng: sửa tỷ lệ cọc mà trang chính sách vẫn ghi
             * số cũ là nói sai với khách về một điều khoản họ sẽ bị áp.
             */
            'payment' => [
                'deposit_percent' => max(1, min(100, (int) config('booking.deposit_percent', 50))),
                'balance_due_days' => (int) config('booking.balance_due_days', 10),
                'reminder_days' => (int) config('booking.balance_reminder_days', 7),
                'final_notice_days' => (int) config('booking.balance_final_notice_days', 2),
            ],
        ], 'Lấy chính sách thành công');
    }

    /**
     * Bảng phí đang áp dụng, hoặc bảng viết trong mã nếu cơ sở dữ liệu chưa có bản nào.
     *
     * Rơi về bảng trong mã chứ không trả rỗng, vì đó đúng là thứ hệ thống đang tính theo - trang
     * chính sách trống trơn sẽ khiến khách tưởng công ty không có chính sách hoàn tiền nào.
     *
     * @return array<string, mixed>
     */
    private function bangPhi(?CancellationPolicy $policy): array
    {
        if (!$policy || $policy->rules->isEmpty()) {
            return [
                'name' => 'Chính sách hủy tiêu chuẩn',
                'description' => null,
                'effective_from' => null,
                'rules' => array_map(
                    fn (array $rule) => [
                        'window' => (new CancellationPolicyRule($rule))->windowLabel(),
                        'refund_percent' => $rule['refund_percent'],
                        'note' => null,
                    ],
                    CancellationPolicyService::DEFAULT_RULES,
                ),
            ];
        }

        return [
            'name' => $policy->name,
            'description' => $policy->description,
            /*
             * Định dạng tay chứ không đưa thẳng Carbon ra.
             *
             * `serializeDate` của model chỉ áp khi chính model được serialize; một Carbon nằm trong
             * mảng thường thì Laravel dùng ISO8601 kèm hậu tố Z, tức tuyên bố giờ Việt Nam đang lưu
             * là giờ UTC. Trình duyệt ở GMT+7 cộng thêm 7 tiếng và mốc hiệu lực lệch hẳn nửa ngày.
             */
            'effective_from' => $policy->effective_from?->format('Y-m-d H:i:s'),
            'rules' => $policy->rules->map(fn (CancellationPolicyRule $rule) => [
                'window' => $rule->windowLabel(),
                'refund_percent' => $rule->refund_percent,
                'note' => $rule->note,
            ])->values(),
        ];
    }
}
