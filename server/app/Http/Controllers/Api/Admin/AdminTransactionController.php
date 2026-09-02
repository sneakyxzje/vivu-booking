<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingPayment;
use App\Traits\LocKhoangThoiGian;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sổ giao dịch tổng — mọi đồng tiền vào và ra, xếp theo thời gian.
 *
 * ## Vì sao cần một màn riêng
 *
 * Sổ vốn chỉ mở được từ bên trong một đơn, tức chỉ trả lời được "khách này đã trả chưa". Kế toán
 * lại hỏi ngược lại mỗi ngày:
 *
 *   - Hôm nay thu bao nhiêu, từ những đơn nào?
 *   - Khoản 5.940.000 trên sao kê là của ai?
 *   - Tháng này tiền mặt bao nhiêu, chuyển khoản bao nhiêu?
 *
 * Không câu nào trả lời được bằng cách mở lần lượt ba mươi tám đơn.
 *
 * ## Tổng tính trên TOÀN BỘ bộ lọc, không riêng trang đang xem
 *
 * Đây là con số đem đi đối chiếu sao kê. Cộng mười dòng đầu rồi gọi đó là doanh thu trong tháng
 * là sai theo cách khó phát hiện nhất — nó vẫn ra một con số trông hợp lý.
 */
class AdminTransactionController extends Controller
{
    use LocKhoangThoiGian;

    public function index(Request $request): JsonResponse
    {
        $filters = $this->docBoLoc($request);

        $transactions = $this->truyVan($filters)
            ->with([
                'booking:id,public_token,customer_name,customer_email,tour_id,status',
                'booking.tour:id,title',
                'recordedBy:id,name',
            ])
            ->latest('paid_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25);

        $transactions->getCollection()->transform(fn (BookingPayment $bt) => $this->dong($bt));

        return $this->success($transactions->toArray() + [
            'totals' => $this->tongKet($filters),
        ], 'Lấy sổ giao dịch thành công');
    }

    /**
     * Xuất ra CSV để đối chiếu với sao kê ngân hàng.
     *
     * Xuất theo đúng bộ lọc đang xem, không xuất tất: người bấm nút vừa lọc ra khoảng ngày họ cần,
     * và một tệp chứa cả năm trời thì họ lại phải lọc lần nữa trong Excel.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->docBoLoc($request);
        $truyVan = $this->truyVan($filters)->with(['booking:id,customer_name', 'recordedBy:id,name']);

        $tenTep = 'so-giao-dich-' . now()->format('Y-m-d') . '.csv';

        return Response::streamDownload(function () use ($truyVan) {
            $out = fopen('php://output', 'w');

            // BOM để Excel bản tiếng Việt đọc đúng dấu.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Thời gian', 'Đơn', 'Khách hàng', 'Loại', 'Vào/Ra', 'Số tiền', 'Hình thức', 'Mã chứng từ', 'Người ghi', 'Ghi chú']);

            $truyVan->orderBy('paid_at')->chunk(500, function ($nhom) use ($out) {
                foreach ($nhom as $bt) {
                    fputcsv($out, [
                        $bt->paid_at?->format('d/m/Y H:i'),
                        'BK-' . $bt->booking_id,
                        $bt->booking?->customer_name,
                        $bt->kindLabel(),
                        in_array($bt->kind, BookingPayment::RA, true) ? 'Ra' : 'Vào',
                        (float) $bt->amount,
                        self::HINH_THUC[$bt->method] ?? $bt->method,
                        $bt->reference,
                        $bt->recordedBy?->name ?? 'Hệ thống',
                        $bt->note,
                    ]);
                }
            });

            fclose($out);
        }, $tenTep, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private const HINH_THUC = [
        'bank_transfer' => 'Chuyển khoản',
        'cash' => 'Tiền mặt',
        'gateway' => 'Cổng thanh toán',
    ];

    /** @return array<string, mixed> */
    private function docBoLoc(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'direction' => ['nullable', Rule::in(['in', 'out'])],
            /*
             * Lọc theo LOẠI bút toán, hẹp hơn `direction`.
             *
             * Chiều tiền trả lời "vào hay ra", loại trả lời "vào bằng đường nào" — tiền cọc khác
             * thanh toán phần còn lại, và phụ thu sự cố lại là túi tiền khác hẳn. Câu hỏi "tháng
             * này thu được bao nhiêu tiền cọc" trước đây phải xuất CSV rồi lọc trong Excel.
             */
            'kind' => ['nullable', Rule::in([
                'deposit',
                'balance',
                BookingPayment::HOAN,
                BookingPayment::PHU_THU,
                BookingPayment::PHU_THU_HOAN,
            ])],
            'method' => ['nullable', Rule::in(['bank_transfer', 'cash', 'gateway'])],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'to.after_or_equal' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.',
        ]);
    }

    /**
     * Truy vấn gốc, dùng chung cho danh sách, tổng và tệp xuất.
     *
     * Một chỗ duy nhất: ba nơi kia mà mỗi nơi tự dựng bộ lọc thì sớm muộn tổng sẽ không khớp với
     * danh sách ngay bên dưới nó, và không ai biết con số nào đúng.
     *
     * @param  array<string, mixed>  $filters
     */
    private function truyVan(array $filters): Builder
    {
        return BookingPayment::query()
            /*
             * Có giờ thì so tới giờ, không có thì lấy trọn ngày.
             *
             * `whereDate` cắt bỏ phần giờ, nên nếu dùng nó thì bộ chọn khoảng thời gian cho người
             * ta chỉnh giờ rồi lặng lẽ bỏ qua — giao diện hứa một thứ máy chủ không làm.
             */
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('paid_at', '>=', $this->mocDau($from)))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('paid_at', '<=', $this->mocCuoi($to)))
            ->when(
                ($filters['direction'] ?? null) === 'in',
                fn ($q) => $q->whereIn('kind', BookingPayment::VAO),
            )
            ->when(
                ($filters['direction'] ?? null) === 'out',
                fn ($q) => $q->whereIn('kind', BookingPayment::RA),
            )
            ->when($filters['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($filters['method'] ?? null, fn ($q, $method) => $q->where('method', $method))
            // Tìm theo mã chứng từ hoặc tên khách — hai thứ người ta cầm trên tay khi đối chiếu.
            ->when($filters['q'] ?? null, function ($q, string $keyword) {
                $keyword = trim($keyword);

                $q->where(function ($sub) use ($keyword) {
                    $sub->where('reference', 'like', "%{$keyword}%")
                        ->orWhereHas('booking', fn ($b) => $b->where('customer_name', 'like', "%{$keyword}%"));
                });
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int>
     */
    private function tongKet(array $filters): array
    {
        $vao = (float) (clone $this->truyVan($filters))->whereIn('kind', BookingPayment::VAO)->sum('amount');
        $ra = (float) (clone $this->truyVan($filters))->whereIn('kind', BookingPayment::RA)->sum('amount');

        return [
            'in' => round($vao),
            'out' => round($ra),
            'net' => round($vao - $ra),
            'count' => (clone $this->truyVan($filters))->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function dong(BookingPayment $bt): array
    {
        return [
            'id' => $bt->id,
            'booking_id' => $bt->booking_id,
            'public_token' => $bt->booking?->public_token,
            'customer_name' => $bt->booking?->customer_name,
            'tour_title' => $bt->booking?->tour?->title,
            'booking_status' => $bt->booking?->status,
            'kind' => $bt->kind,
            'kind_label' => $bt->kindLabel(),
            // Chiều tiền tính ở máy chủ, không để giao diện tự đoán từ `kind`: thêm một loại bút
            // toán mới mà quên sửa giao diện thì nó lặng lẽ bị cộng vào nhầm phía.
            'direction' => in_array($bt->kind, BookingPayment::RA, true) ? 'out' : 'in',
            'amount' => (float) $bt->amount,
            'method' => $bt->method,
            'method_label' => self::HINH_THUC[$bt->method] ?? $bt->method,
            'reference' => $bt->reference,
            'note' => $bt->note,
            'paid_at' => $bt->paid_at?->toDateTimeString(),
            'recorded_by' => $bt->recordedBy?->name,
        ];
    }
}
