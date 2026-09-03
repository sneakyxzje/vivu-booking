<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'public_token',
    'tour_id',
    'customer_id',
    'guest_id',
    'tour_schedule_id',
    'customer_name',
    'customer_email',
    'customer_phone',
    'departure_date',
    'guests',
    'seats',
    'adult_count',
    'child_count',
    'infant_count',
    // Đơn giá chép lại lúc đặt. Chứng từ đọc từ đây chứ không đọc qua tour, cùng lý do với
    // `cancellation_policy_id`: sửa giá tour về sau không được đổi giấy tờ của đơn đã bán.
    'adult_price',
    'child_price',
    'infant_price',
    'total_amount',
    'departure_reminder_sent_at',
    'balance_reminder_sent_at',
    'balance_final_notice_at',
    'discount_code_id',
    'discount_code',
    'discount_amount',
    'status',
    'completed_at',
    'expires_at',
    'note',
    'cancel_reason',
    'cancel_type',
    'cancelled_at',
    'cancelled_by',
    'seats_released',
    'seats_released_at',
    'seats_released_by',
    'refund_amount',
    'refund_bank_account',
    'refund_bank_name',
    'refund_account_holder',
    'cancellation_plan',
    'cancellation_policy_id',
    // Mốc khách tích ô "đã đọc và đồng ý" lúc đặt. Bằng chứng cho việc họ được xem điều khoản
    // TRƯỚC khi trả tiền, không phải sau khi muốn hủy.
    'terms_accepted_at',
    'vnpay_transaction_no',
    'group_booking_request_id',
    'paid_at',
    'confirmed_at',
])]
class Booking extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'departure_reminder_sent_at' => 'datetime',
            'balance_reminder_sent_at' => 'datetime',
            'balance_final_notice_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'seats_released_at' => 'datetime',
            'seats_released' => 'boolean',
        ];
    }

    /**
     * Đơn đã hủy nhưng chỗ chưa được trả về kho.
     *
     * Đây là ghế chết: chỗ trống về mặt vật lý nhưng chưa bán lại được vì phòng và suất ăn
     * đã chốt theo danh sách gửi nhà cung cấp. Điều hành mở lại thủ công khi xin thêm được suất.
     */
    public function scopeWithHeldSeats($query)
    {
        return $query->where('status', 'cancelled')->where('seats_released', false);
    }

    /**
     * Hạn khách phải trả nốt phần còn lại.
     *
     * Neo vào NGÀY KHỞI HÀNH chứ không vào hạn chốt danh sách, và đó là chủ ý. "Thanh toán đủ trước
     * ngày đi 10 ngày" là câu khách đọc một lần là hiểu; "trước hạn chốt danh sách" thì phải giải
     * thích hạn chốt là gì — một khái niệm nội bộ giữa công ty và nhà cung cấp, không phải việc của
     * khách. Đây cũng là cách các hãng lữ hành nội địa vẫn ghi trong điều kiện tour.
     *
     * Không lưu thành cột riêng vì nó suy ra được và luôn đi theo ngày khởi hành: đơn được chuyển
     * sang chuyến khác thì hạn tự dịch theo, không cần ai nhớ cập nhật.
     *
     * Trả null khi đơn không gắn chuyến nào — lúc ấy không có mốc nào để đếm ngược.
     */
    public function balanceDueAt(): ?\Illuminate\Support\Carbon
    {
        $khoiHanh = $this->schedule?->start_date ?? $this->departure_date;

        if (!$khoiHanh) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($khoiHanh)
            ->subDays((int) config('booking.balance_due_days', 10));
    }

    /** Số tiền cọc phải trả khi đặt, theo tỷ lệ cấu hình. */
    public function depositAmount(): float
    {
        $tyLe = max(1, min(100, (int) config('booking.deposit_percent', 50)));

        return round((float) $this->total_amount * $tyLe / 100);
    }

    /**
     * Số ghế đơn này chiếm của chuyến.
     *
     * Khác `guests`, là số NGƯỜI đi: em bé dưới hai tuổi ngồi cùng bố mẹ nên không chiếm ghế riêng.
     * Xem `PassengerPolicyService` và migration 2026_09_02_000002.
     *
     * Đọc qua hàm này thay vì đọc thẳng cột, để đơn tạo trước migration - cột `seats` bằng 0 vì
     * chưa được backfill - vẫn lùi về `guests` thay vì tuyên bố mình không chiếm chỗ nào.
     */
    public function seatsTaken(): int
    {
        return (int) ($this->seats ?: $this->guests);
    }

    /** Số ghế tính từ cơ cấu khách: người lớn và trẻ em chiếm ghế, em bé thì không. */
    public static function tinhSoGhe(int $adult, int $child): int
    {
        return $adult + $child;
    }

    /**
     * Người đang cầm mã tra cứu có chứng minh được họ là chủ đơn không.
     *
     * Mã tra cứu là chuỗi ngẫu nhiên khó đoán, nhưng nó đi trong thư, và thư thì được chuyển tiếp,
     * mở trên máy dùng chung, nằm lại trong lịch sử trình duyệt. Với những thao tác chạm vào dữ
     * liệu cá nhân của cả đoàn — đọc đầy đủ số giấy tờ, hay sửa danh sách — mã thôi là chưa đủ.
     *
     * Yêu cầu thêm đúng địa chỉ thư đã dùng khi đặt: người thật luôn có, người nhặt được đường dẫn
     * thì không. So không phân biệt hoa thường và bỏ khoảng trắng thừa, vì người ta gõ tay.
     */
    public function khopEmail(?string $email): bool
    {
        if (!$email || !$this->customer_email) {
            return false;
        }

        return mb_strtolower(trim($email)) === mb_strtolower(trim($this->customer_email));
    }

    /**
     * Số giấy tờ dạng che, chỉ giữ bốn ký tự cuối.
     *
     * Đủ để chủ đơn nhận ra mình đã khai đúng người, không đủ để người khác chép lại.
     */
    public static function cheSoGiayTo(?string $so): ?string
    {
        $so = trim((string) $so);

        if ($so === '') {
            return null;
        }

        if (mb_strlen($so) <= 4) {
            return str_repeat('•', mb_strlen($so));
        }

        return str_repeat('•', mb_strlen($so) - 4) . mb_substr($so, -4);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending'
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /**
     * Tour của đơn — đọc được cả khi tour đã bị xóa mềm.
     *
     * Không có `withTrashed` thì xóa một tour làm mọi đơn cũ của nó mất tên tour trên màn hình
     * và trên chứng từ. Đó đúng là thứ việc xóa mềm sinh ra để tránh.
     */
    public function tour()
    {
        return $this->belongsTo(Tour::class)->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function schedule()
    {
        return $this->belongsTo(TourSchedule::class, 'tour_schedule_id');
    }

    public function paymentLogs()
    {
        return $this->hasMany(PaymentLog::class);
    }

    /**
     * Sổ giao dịch: từng khoản thu và hoàn, chỉ thêm dòng không ghi đè.
     *
     * Hiện chỉ đơn đoàn có dòng ở đây - đơn lẻ vẫn trả một lần qua cổng và đọc `paid_at`.
     * `CancellationPolicyService::paidAmount()` tự chọn nguồn theo việc sổ có dòng hay không.
     */
    public function payments()
    {
        return $this->hasMany(BookingPayment::class);
    }

    /**
     * Các khoản phụ thu và hoàn sinh ra từ sự cố dọc đường.
     *
     * Không liên quan gì tới giá tour: khách trả 4,5 triệu là đã trả xong tour. Đây là những thứ
     * xảy ra sau khi đoàn lên đường mà không ai lường trước - kẹt bão phải ở thêm đêm, hoặc ngược
     * lại, buổi tham quan đã bán mà không đi được nên phải hoàn.
     *
     * Khách phải thấy được các dòng này trong đơn của mình, nếu không thì hệ thống lập một khoản
     * phải trả rồi không nói với người phải trả nó.
     */
    public function surcharges()
    {
        return $this->hasMany(BookingSurcharge::class);
    }

    /** Yêu cầu đoàn đã sinh ra đơn này, null với đơn lẻ. */
    public function groupRequest()
    {
        return $this->belongsTo(GroupBookingRequest::class, 'group_booking_request_id');
    }

    /** Đơn này là đơn đoàn (sinh từ luồng yêu cầu - báo giá - chốt). */
    public function isGroup(): bool
    {
        return $this->group_booking_request_id !== null;
    }

    public function passengers()
    {
        return $this->hasMany(BookingPassenger::class);
    }

    public function discountCode()
    {
        return $this->belongsTo(DiscountCode::class);
    }

    /**
     * Chính sách hủy đã sao chép lúc đặt. Đọc từ đây chứ không đọc qua tour, vì tour có thể
     * đã đổi sang chính sách khác sau khi khách đặt.
     */
    public function cancellationPolicy()
    {
        return $this->belongsTo(CancellationPolicy::class);
    }
}


