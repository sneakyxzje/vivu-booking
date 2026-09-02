<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\BookingPaymentService;
use App\Services\RefundAccountService;
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

    /**
     * Những đơn khách còn nợ công ty.
     *
     * ## Vì sao cần một màn riêng
     *
     * Hệ thống đã có `refundQueue()` — công ty nợ khách. Chiều ngược lại thì không có gì cả: muốn
     * biết một đơn còn thiếu bao nhiêu phải mở đúng đơn ấy ra xem.
     *
     * Trước đây câu hỏi ấy hiếm gặp vì đơn lẻ trả đủ một lần qua cổng. Từ khi đơn trả nhiều đợt —
     * cọc trước, phần còn lại sau, và có thể bằng chuyển khoản hay tiền mặt — nó thành câu kế toán
     * hỏi mỗi ngày: *hôm nay những đơn nào còn nợ, tổng bao nhiêu, đơn nào sắp đi mà chưa thu đủ.*
     *
     * ## Vì sao phải cộng sổ chứ không chỉ đọc `paid_at`
     *
     * Bản đầu của màn này lọc mỗi `paid_at IS NULL`, với lý lẽ rằng `BookingPaymentService::record()`
     * đóng mốc ấy đúng lúc thu đủ. Lý lẽ ấy chỉ đúng với tiền **đi qua service** — dữ liệu dựng bằng
     * seeder, nhập từ hệ thống cũ, hay sửa tay trong cơ sở dữ liệu thì ghi thẳng vào bảng bút toán
     * và mốc kia không bao giờ đóng. Kết quả: đơn đã thu đủ vẫn nằm trong danh sách đòi nợ, với cột
     * "còn thiếu" ghi 0 đồng — tự nó mâu thuẫn.
     *
     * Nên điều kiện thật nằm ở tổng sổ: **tổng các khoản THU nhỏ hơn giá đơn**. Đó là định nghĩa của
     * "khách còn nợ", không phải một dấu hiệu gián tiếp của nó.
     *
     * `paid_at IS NULL` vẫn giữ, nhưng cho việc khác: các đơn tạo TRƯỚC khi sổ mở cho đơn lẻ không
     * có bút toán nào cả, và với chúng mốc ấy là bằng chứng duy nhất còn lại rằng tiền đã về. Thiếu
     * vế này thì mọi đơn cũ đều bị đòi lại tiền một lần nữa.
     *
     * Hai vế cùng nhau cũng loại đúng nhóm không nên có mặt ở đây: đơn đã thu đủ rồi được hoàn bớt
     * một phần. Đơn ấy còn thiếu so với giá đơn, nhưng phần thiếu là tiền công ty vừa trả lại khách
     * chứ không phải khách nợ công ty — chỗ của nó là tab phải trả.
     *
     * ## Vì sao bỏ đơn đang giữ chỗ
     *
     * Đơn `pending` chưa trả đồng nào theo định nghĩa, và nó tự hủy sau mười phút. Gọi đó là công
     * nợ thì danh sách đầy những dòng sẽ tự biến mất, và con số tổng nói dối.
     */
    public function receivableQueue(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            // Chỉ những đơn sắp khởi hành trong ngần này ngày — thứ cần đòi trước.
            'within_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /*
         * Tổng đã THU của đơn, tính ngay trong SQL.
         *
         * Viết bằng truy vấn con thay vì kéo mọi đơn về rồi cộng bằng PHP: danh sách này phân trang,
         * và lọc bằng PHP sau khi phân trang cho ra những trang trống một cách khó hiểu.
         *
         * Chỉ cộng các loại THU của giá tour. Phụ thu sự cố là túi tiền khác — gộp vào thì một đơn
         * còn nợ tiền tour bỗng biến mất khỏi danh sách chỉ vì khách đã trả tiền một đêm phòng
         * chạy bão.
         */
        $daThu = '(SELECT COALESCE(SUM(bp.amount), 0) FROM booking_payments bp'
            . ' WHERE bp.booking_id = bookings.id AND bp.kind IN (?, ?))';

        $truyVan = fn () => Booking::query()
            ->whereIn('status', BookingStatus::paidValues())
            ->whereNull('paid_at')
            ->whereRaw($daThu . ' < bookings.total_amount', BookingPayment::THU)
            ->when($filters['q'] ?? null, function ($q, string $tuKhoa) {
                $tuKhoa = trim($tuKhoa);

                if (preg_match('/^bk[-\s]?(\d+)$/i', $tuKhoa, $khop)) {
                    return $q->whereKey((int) $khop[1]);
                }

                return $q->where(fn ($sub) => $sub
                    ->where('customer_name', 'like', "%{$tuKhoa}%")
                    ->orWhere('customer_email', 'like', "%{$tuKhoa}%")
                    ->orWhere('customer_phone', 'like', "%{$tuKhoa}%"));
            })
            ->when($filters['within_days'] ?? null, fn ($q, int $soNgay) => $q
                ->whereHas('schedule', fn ($s) => $s
                    ->whereBetween('start_date', [now(), now()->addDays($soNgay)])));

        $bookings = $truyVan()
            ->with(['tour:id,title', 'schedule:id,start_date,booking_deadline', 'payments'])
            // Đơn sắp khởi hành lên trước: đó là tiền cần đòi gấp nhất, vì sau khi đoàn đi rồi thì
            // đòi khó hơn nhiều.
            ->orderBy('departure_date')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 20);

        $dong = collect($bookings->items())->map(function (Booking $booking) {
            $hanChot = $booking->schedule?->booking_deadline
                ?? $booking->schedule?->defaultBookingDeadline();

            return [
                'id' => $booking->id,
                'public_token' => $booking->public_token,
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_email,
                'customer_phone' => $booking->customer_phone,
                'tour_title' => $booking->tour?->title,
                'start_date' => $booking->schedule?->start_date,
                'total_amount' => round((float) $booking->total_amount),
                'net_paid' => $this->payments->netPaid($booking),
                'balance_due' => $this->payments->balanceDue($booking),
                /*
                 * Hạn chốt danh sách làm hạn thu tiền.
                 *
                 * Đó là mốc điều hành phải trả tiền cho khách sạn và nhà xe, nên cũng là mốc muộn
                 * nhất tiền của khách phải về — cùng cách bản in hợp đồng đang nói với khách.
                 */
                'due_by' => $hanChot?->toDateTimeString(),
                'overdue' => $hanChot !== null && now()->gte($hanChot),
                'status' => $booking->status,
            ];
        })->values();

        return $this->success([
            'data' => $dong,
            'current_page' => $bookings->currentPage(),
            'last_page' => $bookings->lastPage(),
            'total' => $bookings->total(),
            // Tổng còn phải thu, tính trên TOÀN BỘ bộ lọc chứ không riêng trang đang xem — cùng
            // nguyên tắc với sổ giao dịch và hàng đợi hoàn tiền.
            'outstanding_total' => $this->tongPhaiThu($truyVan()),
        ], 'Lấy danh sách công nợ phải thu thành công');
    }

    /** @param  \Illuminate\Database\Eloquent\Builder  $truyVan */
    private function tongPhaiThu($truyVan): float
    {
        return round($truyVan->with('payments')->get()
            ->sum(fn (Booking $booking) => $this->payments->balanceDue($booking)));
    }

    /**
     * Điều hành nhập hộ tài khoản nhận tiền hoàn.
     *
     * Khách đọc số tài khoản qua điện thoại là chuyện thường, nhất là với người lớn tuổi không quen
     * mở lại trang tra cứu. Cùng service với đường khách tự nhập nên hai cửa chịu chung một luật và
     * để lại cùng một loại vết trong nhật ký.
     */
    public function updateRefundAccount(Request $request, int $bookingId): JsonResponse
    {
        $validated = $request->validate(
            RefundAccountService::validationRules(),
            RefundAccountService::validationMessages(),
        );

        $booking = Booking::query()->find($bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn hàng', 404);
        }

        app(RefundAccountService::class)->update($booking, $validated, $request->user());

        return $this->success(
            $this->thongTinNganHang($booking->fresh()),
            'Đã lưu tài khoản nhận tiền hoàn.',
        );
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
