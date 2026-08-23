<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một bút toán trên sổ giao dịch của đơn hàng - phần lõi của điểm 12.
 *
 * Chỉ thêm dòng, không sửa dòng cũ: ghi nhầm thì ghi một dòng điều chỉnh ngược lại. Số đã thu là
 * TỔNG của sổ chứ không phải một cột bị ghi đè, vì tiền là thứ phải đối soát được từng bước -
 * "đơn này đã thu 30 triệu" phải trả lời được câu "thu những lần nào, ai ghi".
 *
 * Số tiền luôn dương; `kind` quyết định dấu (deposit/balance cộng, refund trừ). Không lưu số âm
 * để không bao giờ phải đoán -5.000.000 nghĩa là hoàn hay là ghi nhầm.
 */
class BookingPayment extends Model
{
    /*
     * Hai túi tiền, cố ý không trộn vào nhau.
     *
     *   THU / HOAN            — tiền của GIÁ TOUR. Đây là thứ chính sách hủy đọc để tính hoàn.
     *   PHU_THU / PHU_THU_HOAN — tiền sinh ra từ SỰ CỐ dọc đường, không liên quan giá tour.
     *
     * Nếu gộp phụ thu vào THU thì `CancellationPolicyService::paidAmount()` sẽ coi khoản khách trả
     * cho một đêm phòng chạy bão là tiền đã trả cho tour, và đem hoàn lại theo bậc phần trăm. Đêm
     * phòng đó đã ở thật rồi.
     *
     * Trên thực tế hai luồng không gặp nhau — phụ thu chỉ sinh ra khi chuyến đã khởi hành, mà
     * chuyến đã khởi hành thì không hủy đơn được nữa. Nhưng dựa vào sự trùng hợp ấy để cho hai
     * loại tiền dùng chung một nhãn là đúng kiểu lỗi dự án này đã gặp bảy lần: một quy tắc đứng
     * được nhờ một quy tắc khác ở xa, cho tới hôm ai đó sửa quy tắc kia.
     */
    public const THU = ['deposit', 'balance'];
    public const HOAN = 'refund';
    public const PHU_THU = 'surcharge';
    public const PHU_THU_HOAN = 'surcharge_refund';

    protected $fillable = [
        'booking_id',
        'booking_surcharge_id',
        'kind',
        'amount',
        'method',
        'reference',
        'note',
        'paid_at',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    /** Giờ Việt Nam dạng mộc, cùng quy ước với các cột thời gian nghiệp vụ khác. */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Dòng sinh ra từ một khoản phụ thu sự cố, nếu có. */
    public function surcharge(): BelongsTo
    {
        return $this->belongsTo(BookingSurcharge::class, 'booking_surcharge_id');
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'deposit' => 'Tiền cọc',
            'balance' => 'Thanh toán phần còn lại',
            'refund' => 'Hoàn tiền',
            self::PHU_THU => 'Thu phụ phí sự cố',
            self::PHU_THU_HOAN => 'Hoàn do sự cố',
            default => $this->kind,
        };
    }
}
