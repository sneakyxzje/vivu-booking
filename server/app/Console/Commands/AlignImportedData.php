<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nắn dữ liệu nhập từ bên ngoài cho khớp mô hình hiện tại.
 *
 * ## Vì sao cần
 *
 * Dữ liệu dựng trên bản mã cũ mang theo giả định của bản ấy: mỗi đơn trả một lần, và cột `paid_at`
 * là câu trả lời cho "đơn này đã trả tiền chưa". Mô hình bây giờ trả lời câu đó bằng **sổ giao
 * dịch** — `paid_at` chỉ còn là cái mốc đóng lại khi đã thu ĐỦ.
 *
 * Chạy `migrate` là đủ để schema khớp: hai migration ngày 02/09 tự backfill `seats`, nắn lại
 * `booked_people`, và chép đơn giá vào từng đơn. Thứ chúng không làm được là dựng lại sổ, vì sổ là
 * dữ liệu nghiệp vụ chứ không phải cấu trúc.
 *
 * ## Hậu quả nếu bỏ qua
 *
 * Đơn có `paid_at` mà sổ trống làm hai hàm trong cùng một lớp nói ngược nhau:
 *
 *   - `paidForTour()` có đường lùi đọc `paid_at` nên trả về nguyên giá đơn — "đã trả đủ".
 *   - `balanceDue()` đi qua `netPaid()`, vốn chỉ cộng sổ, nên trả về nguyên giá đơn — "chưa trả gì".
 *
 * Nên bản in hợp đồng ghi đã thu đủ, trong khi màn công nợ phải thu đòi lại từ đầu, và trang tra
 * cứu dựng cho khách một liên kết thanh toán cho khoản họ đã trả xong từ lâu.
 *
 * Lệnh này viết một bút toán cho mỗi đơn như vậy, để sổ thành nguồn duy nhất trên toàn bộ dữ liệu.
 *
 * ## Cái nó cố ý KHÔNG làm
 *
 * Không đụng tới `terms_accepted_at`. Cột ấy là bằng chứng khách đã đọc và đồng ý bảng phí hủy
 * trước khi trả tiền — điền hộ nó là bịa ra một sự đồng ý chưa từng xảy ra, và đó đúng là thứ giấy
 * tờ mà mọi khiếu nại hoàn tiền lôi ra. Đơn cũ hiện "chưa ghi nhận đồng ý" là nói thật.
 *
 * Không đoán đơn `confirmed` mà không có `paid_at` là đã trả tiền. Trạng thái ấy có hai nghĩa và
 * không có cách nào phân biệt từ bên ngoài; đoán sai chiều nào cũng là bịa một khoản tiền.
 */
class AlignImportedData extends Command
{
    protected $signature = 'data:align-imported {--dry-run : Chỉ liệt kê, không ghi gì}';

    protected $description = 'Nắn dữ liệu nhập từ bản mã cũ cho khớp mô hình sổ giao dịch hiện tại';

    public function handle(): int
    {
        $chiXem = (bool) $this->option('dry-run');

        $this->dungLaiSo($chiXem);
        $this->baoCaoConLech();

        if ($chiXem) {
            $this->newLine();
            $this->info('Đây là bản xem trước. Bỏ --dry-run để ghi thật.');
        }

        return self::SUCCESS;
    }

    /**
     * Viết bút toán cho đơn đã đóng mốc `paid_at` mà sổ chưa có dòng nào.
     *
     * Số tiền lấy đúng giá trị đơn, và thời điểm lấy đúng `paid_at` — đó là những gì bản mã cũ biết
     * về khoản tiền ấy, không hơn. Ghi `method = 'imported'` để sau này ai đối chiếu sổ với sao kê
     * ngân hàng còn biết dòng nào là dựng lại chứ không phải một giao dịch có chứng từ.
     */
    private function dungLaiSo(bool $chiXem): void
    {
        $can = Booking::query()
            ->whereNotNull('paid_at')
            ->where('total_amount', '>', 0)
            ->whereDoesntHave('payments')
            ->orderBy('id')
            ->get(['id', 'total_amount', 'paid_at']);

        if ($can->isEmpty()) {
            $this->info('Sổ giao dịch đã đầy đủ: không đơn nào đóng mốc thanh toán mà thiếu bút toán.');

            return;
        }

        $this->warn(sprintf(
            '%d đơn đã đóng mốc thanh toán nhưng sổ trống — sẽ dựng lại bút toán:',
            $can->count(),
        ));

        foreach ($can as $don) {
            $this->line(sprintf(
                '  Đơn #%d: %s đ, ghi theo mốc %s',
                $don->id,
                number_format((float) $don->total_amount, 0, ',', '.'),
                // `paid_at` không được ép kiểu ngày trên model, nên đọc ra là chuỗi thô. Parse thay
                // vì gọi thẳng `format()`, và cũng để chịu được cả dữ liệu nhập có định dạng lạ.
                $this->ngay($don->paid_at)?->format('d/m/Y') ?? '?',
            ));
        }

        if ($chiXem) {
            return;
        }

        DB::transaction(function () use ($can) {
            foreach ($can as $don) {
                BookingPayment::query()->create([
                    'booking_id' => $don->id,
                    'kind' => 'balance',
                    'amount' => round((float) $don->total_amount),
                    'method' => 'imported',
                    'reference' => 'IMPORT-' . $don->id,
                    'note' => 'Dựng lại từ mốc paid_at của dữ liệu nhập từ bản mã cũ.',
                    'paid_at' => $don->paid_at,
                ]);
            }
        });

        $this->info(sprintf('Đã dựng %d bút toán.', $can->count()));
    }

    /** Đọc một mốc thời gian có thể là chuỗi thô từ dữ liệu nhập, hoặc null. */
    private function ngay(mixed $gia): ?\Illuminate\Support\Carbon
    {
        if (!$gia) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($gia);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Những chỗ còn lệch mà lệnh này CỐ Ý không tự sửa.
     *
     * Báo cáo chứ không nắn: mỗi nhóm dưới đây cần một quyết định của con người, và tự quyết hộ thì
     * hoặc bịa ra tiền, hoặc bịa ra chữ ký.
     */
    private function baoCaoConLech(): void
    {
        $this->newLine();
        $this->info('Còn lại — cần người xem, lệnh này không tự sửa:');

        $khongMocKhongSo = Booking::query()
            ->whereNull('paid_at')
            ->whereIn('status', ['confirmed', 'paid'])
            ->whereDoesntHave('payments')
            ->count();

        $this->line(sprintf(
            '  %d đơn đã xác nhận nhưng không có mốc thanh toán lẫn bút toán nào.',
            $khongMocKhongSo,
        ));
        $this->line('    → Có thể khách đã trả mà chưa ai ghi sổ, cũng có thể chưa trả. Không đoán được');
        $this->line('      từ bên ngoài, nên chúng hiện ở màn công nợ phải thu để điều hành xử lý từng đơn.');
        $this->line('      Lệnh hủy tự động cố ý bỏ qua nhóm này.');

        $chuaDongY = Booking::query()->whereNull('terms_accepted_at')->count();

        $this->line(sprintf('  %d đơn chưa ghi nhận đồng ý điều khoản.', $chuaDongY));
        $this->line('    → Cố ý để trống. Điền hộ là bịa ra bằng chứng khách đã đọc bảng phí hủy,');
        $this->line('      và đó chính là tờ giấy mọi khiếu nại hoàn tiền lôi ra.');

        $this->newLine();
        $this->line('Kiểm số chỗ sau khi nắn:  php artisan bookings:check-seat-consistency');
    }
}
