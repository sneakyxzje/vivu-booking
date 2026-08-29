<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\BookingPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sổ giao dịch của một đơn, và hàng đợi hoàn tiền.
 *
 * Trước đây hai điểm cuối đầu nằm trong `AdminGroupBookingController` và chỉ nhận đơn đoàn. Từ
 * khi đơn lẻ cũng trả nhiều đợt — cọc trước, phần còn lại sau, và có thể bằng chuyển khoản hay
 * tiền mặt tại văn phòng — chúng áp cho mọi đơn, nên chuyển ra đây.
 */
class AdminBookingPaymentController extends Controller
{
    public function __construct(private BookingPaymentService $payments)
    {
    }

    public function index(int $bookingId): JsonResponse
    {
        $booking = Booking::query()->with('payments.recordedBy:id,name')->find($bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn hàng', 404);
        }

        return $this->success([
            'total_amount' => round((float) $booking->total_amount),
            'net_paid' => $this->payments->netPaid($booking),
            'balance_due' => $this->payments->balanceDue($booking),
            'paid_in_full' => $booking->paid_at !== null,
            // Nghĩa vụ hoàn sau khi hủy, và phần trong đó đã thực trả.
            'refund_due' => round((float) ($booking->refund_amount ?? 0)),
            'refunded' => $this->payments->refunded($booking),
            'refund_outstanding' => $this->payments->refundOutstanding($booking),
            'refund_bank' => $this->thongTinNganHang($booking),
            'entries' => $booking->payments->map(fn (BookingPayment $bt) => [
                'id' => $bt->id,
                'kind' => $bt->kind,
                'kind_label' => $bt->kindLabel(),
                'amount' => (float) $bt->amount,
                'method' => $bt->method,
                'reference' => $bt->reference,
                'note' => $bt->note,
                'paid_at' => $bt->paid_at,
                'recorded_by' => $bt->recordedBy?->name,
            ])->values(),
        ], 'Lấy sổ giao dịch thành công');
    }

    public function store(Request $request, int $bookingId): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:deposit,balance,refund'],
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['nullable', 'in:bank_transfer,cash,gateway'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $booking = Booking::query()->find($bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn hàng', 404);
        }

        $this->payments->record(
            $booking,
            $data['kind'],
            (float) $data['amount'],
            $data['method'] ?? null,
            $data['reference'] ?? null,
            $data['note'] ?? null,
            $request->user(),
        );

        $daSua = $booking->fresh();

        return $this->success([
            'net_paid' => $this->payments->netPaid($daSua),
            'balance_due' => $this->payments->balanceDue($daSua),
            'refund_outstanding' => $this->payments->refundOutstanding($daSua),
            'paid_in_full' => $daSua->paid_at !== null,
        ], 'Đã ghi vào sổ giao dịch.');
    }

    /**
     * Những đơn công ty còn nợ tiền khách.
     *
     * Trước màn hình này, `refund_amount` chỉ là một con số nằm trên bản ghi: hệ thống nói với
     * khách "bạn được hoàn 2.400.000đ" rồi thôi. Không chỗ nào trả lời được câu "đơn nào đã
     * chuyển tiền, đơn nào chưa" — mà đó chính là câu kế toán phải trả lời hằng ngày.
     *
     * Còn nợ = `refund_amount` trừ tổng các dòng `refund` trong sổ. Ghi một khoản hoàn qua
     * `store()` ở trên là cách một đơn rời khỏi danh sách này.
     */
    public function refundQueue(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'settled' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $daTra = (bool) ($filters['settled'] ?? false);

        $bookings = Booking::query()
            ->with(['tour:id,title', 'schedule:id,start_date', 'payments'])
            ->whereNotNull('refund_amount')
            ->where('refund_amount', '>', 0)
            ->latest('cancelled_at')
            ->paginate($filters['per_page'] ?? 20);

        /*
         * Lọc "đã trả xong hay chưa" bằng PHP sau khi lấy trang về.
         *
         * Điều kiện là một phép so sánh giữa một cột và tổng của bảng con, và viết nó thành SQL
         * chạy giống nhau trên cả SQLite lẫn MySQL thì rối hơn giá trị nó mang lại: số đơn có
         * nghĩa vụ hoàn luôn nhỏ, và trang này chỉ điều hành mở.
         */
        $dong = collect($bookings->items())
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'public_token' => $booking->public_token,
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_email,
                'customer_phone' => $booking->customer_phone,
                'tour_title' => $booking->tour?->title,
                'start_date' => $booking->schedule?->start_date,
                'cancelled_at' => $booking->cancelled_at?->toDateTimeString(),
                'cancel_reason' => $booking->cancel_reason,
                'refund_due' => round((float) $booking->refund_amount),
                'refunded' => $this->payments->refunded($booking),
                'refund_outstanding' => $this->payments->refundOutstanding($booking),
                'refund_bank' => $this->thongTinNganHang($booking),
            ])
            ->filter(fn (array $row) => $daTra
                ? $row['refund_outstanding'] <= 0
                : $row['refund_outstanding'] > 0)
            ->values();

        return $this->success([
            'data' => $dong,
            'current_page' => $bookings->currentPage(),
            'last_page' => $bookings->lastPage(),
            // Tổng tiền còn nợ khách, tính trên toàn bộ chứ không riêng trang đang xem: đây là
            // con số kế toán cần, và nó vô nghĩa nếu chỉ cộng mười đơn đầu.
            'outstanding_total' => $this->tongConNo(),
        ], 'Lấy danh sách hoàn tiền thành công');
    }

    /** @return array<string, string|null>|null */
    private function thongTinNganHang(Booking $booking): ?array
    {
        if (!$booking->refund_bank_account) {
            return null;
        }

        return [
            'account_number' => $booking->refund_bank_account,
            'bank_name' => $booking->refund_bank_name,
            'account_holder' => $booking->refund_account_holder,
        ];
    }

    private function tongConNo(): float
    {
        return Booking::query()
            ->with('payments')
            ->whereNotNull('refund_amount')
            ->where('refund_amount', '>', 0)
            ->get()
            ->sum(fn (Booking $booking) => $this->payments->refundOutstanding($booking));
    }
}
