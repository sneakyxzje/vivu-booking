<?php

namespace App\Services\Sandbox;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingPayment;
use App\Services\BookingPaymentService;

/**
 * Biên bản của một lần chạy kịch bản: các bước, sổ giao dịch, và nhật ký đơn.
 *
 * ## Vì sao ba thứ này phải đi cùng nhau
 *
 * Các bước nói **chuyện gì đã xảy ra**. Sổ giao dịch nói **tiền đi đâu**. Nhật ký nói **hệ thống
 * ghi lại thế nào**. Thiếu một trong ba là người xem phải tin lời thay vì đối chiếu.
 *
 * Sổ đặc biệt quan trọng ở đây: câu hỏi hay bị vặn nhất về mô hình đặt cọc không phải "đơn có bị
 * hủy không" mà là "vậy tiền của khách đi đâu". Một bảng có dấu cộng trừ rõ ràng trả lời câu ấy
 * nhanh hơn mọi đoạn giải thích.
 */
class SandboxTranscript
{
    /** @var array<int, SandboxStep> */
    private array $buoc = [];

    public function __construct(
        private readonly string $id,
        private readonly string $nhom,
        private readonly string $ten,
        private readonly string $chungMinh,
        private readonly BookingPaymentService $payments,
    ) {
    }

    public function them(string $lamGi, string $kyVong, string $ketQua, bool $dat): void
    {
        $this->buoc[] = new SandboxStep(count($this->buoc) + 1, $lamGi, $kyVong, $ketQua, $dat);
    }

    /** Kịch bản đạt khi mọi bước đều đạt. Một bước hỏng là cả kịch bản hỏng. */
    public function dat(): bool
    {
        foreach ($this->buoc as $b) {
            if (!$b->dat) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, Booking>  $donLienQuan
     * @return array<string, mixed>
     */
    public function toArray(array $donLienQuan = []): array
    {
        return [
            'id' => $this->id,
            'nhom' => $this->nhom,
            'ten' => $this->ten,
            'chung_minh' => $this->chungMinh,
            'dat' => $this->dat(),
            'buoc' => array_map(fn (SandboxStep $b) => $b->toArray(), $this->buoc),
            'don' => array_map(fn (Booking $d) => $this->tomTatDon($d->fresh()), $donLienQuan),
        ];
    }

    /** @return array<string, mixed> */
    private function tomTatDon(Booking $don): array
    {
        return [
            'ma' => 'BK-' . $don->id,
            'trang_thai' => $don->status,
            'tong_don' => round((float) $don->total_amount),
            'da_thu' => $this->payments->netPaid($don),
            'con_thieu' => $this->payments->balanceDue($don),
            'nghia_vu_hoan' => $this->payments->refundOutstanding($don),
            'han_tra_not' => $don->balanceDueAt()?->format('d/m/Y'),
            'cho_da_tra' => (bool) $don->seats_released,
            'so_giao_dich' => $this->soGiaoDich($don),
            'nhat_ky' => $this->nhatKy($don),
        ];
    }

    /**
     * Sổ giao dịch của đơn, kèm chiều tiền.
     *
     * `chieu` tính ở đây chứ không để giao diện tự suy từ `kind`: quy ước loại nào là vào, loại nào
     * là ra nằm ở `BookingPayment`, và chép nó sang TypeScript là dựng bản thứ hai của một luật.
     *
     * @return array<int, array<string, mixed>>
     */
    private function soGiaoDich(Booking $don): array
    {
        return BookingPayment::query()
            ->where('booking_id', $don->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn (BookingPayment $dong) => [
                'loai' => $dong->kind,
                'nhan' => match ($dong->kind) {
                    'deposit' => 'Tiền cọc',
                    'balance' => 'Thanh toán nốt',
                    'refund' => 'Hoàn tiền',
                    'surcharge' => 'Phụ thu',
                    'surcharge_refund' => 'Hoàn phụ thu',
                    default => $dong->kind,
                },
                'chieu' => in_array($dong->kind, BookingPayment::VAO, true) ? '+' : '-',
                'so_tien' => round((float) $dong->amount),
                'phuong_thuc' => $dong->method,
                'ghi_chu' => $dong->note,
                'luc' => $dong->paid_at?->format('d/m/Y H:i'),
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function nhatKy(Booking $don): array
    {
        return BookingAuditLog::query()
            ->where('booking_id', $don->getKey())
            ->orderBy('id')
            ->get()
            // Trạng thái nằm trong hai cột JSON `old_values`/`new_values`, không có cột riêng —
            // nhật ký này ghi mọi loại thay đổi chứ không riêng đổi trạng thái.
            ->map(fn (BookingAuditLog $dong) => [
                'hanh_dong' => $dong->action,
                'tu' => data_get($dong->old_values, 'status'),
                'sang' => data_get($dong->new_values, 'status'),
                'ly_do' => $dong->reason,
                'luc' => $dong->created_at?->format('d/m/Y H:i'),
            ])
            ->all();
    }
}
