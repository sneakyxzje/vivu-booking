<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingContract;
use App\Models\User;
use App\Support\GioVietNam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Q - Cấp hợp đồng du lịch cho một đơn.
 *
 * Hợp đồng ở đây là **một cách đọc đơn hàng**, không phải một bản sao của nó. Bảng
 * `booking_contracts` chỉ giữ số hợp đồng và các mốc thời gian; giá, lịch trình, chính sách hủy
 * đều lấy từ đơn lúc in. Chép chúng sang bảng hợp đồng thì thành hai nguồn cho cùng một con số,
 * và sớm muộn hai nguồn nói khác nhau.
 *
 * Riêng chính sách hủy thì đơn đã tự chép sẵn lúc đặt (`bookings.cancellation_policy_id`), nên
 * hợp đồng in ra đúng bậc hoàn có hiệu lực lúc khách mua, không phải bậc hiện hành.
 */
class ContractService
{
    /** Liên kết in hợp đồng sống bao lâu. Đủ để mở và in, không đủ để trôi nổi. */
    private const GIO_SONG_CUA_LIEN_KET = 24;

    /**
     * Cấp hợp đồng, hoặc trả lại bản đã cấp.
     *
     * Cố ý **không sinh số mới khi gọi lại**. Khách đang cầm bản in ghi số cũ; cấp số thứ hai cho
     * cùng một đơn là tạo ra hai hợp đồng cho một giao dịch, và không ai biết bản nào có hiệu lực.
     */
    public function issue(Booking $booking, User $actor): BookingContract
    {
        $this->assertCoTheCapHopDong($booking);

        return DB::transaction(function () use ($booking, $actor) {
            $daCo = BookingContract::query()
                ->where('booking_id', $booking->getKey())
                ->lockForUpdate()
                ->first();

            if ($daCo) {
                return $daCo;
            }

            return BookingContract::query()->create([
                'booking_id' => $booking->getKey(),
                'contract_number' => $this->soTiepTheo(),
                'issued_at' => now(),
                'issued_by' => $actor->getKey(),
            ]);
        });
    }

    /** Ghi nhận khách đã ký. */
    public function markSigned(BookingContract $contract, ?string $ghiChu = null): BookingContract
    {
        if ($contract->daKy()) {
            throw new BusinessRuleException('Hợp đồng này đã ghi nhận ký rồi.');
        }

        $contract->forceFill([
            'signed_at' => now(),
            'signed_note' => $ghiChu ? trim($ghiChu) : null,
        ])->save();

        return $contract->fresh();
    }

    /**
     * Liên kết mở bản in.
     *
     * Dùng liên kết có chữ ký thay vì bắt đăng nhập, vì trang in phải mở được bằng một thẻ <a>
     * bình thường — API dùng token Bearer, mà thẻ <a> thì không gắn được tiêu đề nào cả.
     *
     * Có hạn dùng nên liên kết lỡ lọt ra ngoài cũng hết hiệu lực; và cùng cơ chế này về sau gửi
     * thẳng cho khách được, không phải làm lại.
     */
    public function printUrl(BookingContract $contract): string
    {
        return URL::temporarySignedRoute(
            'contracts.print',
            now()->addHours(self::GIO_SONG_CUA_LIEN_KET),
            ['contract' => $contract->getKey()],
        );
    }

    /**
     * Số kế tiếp trong năm, dạng HD-2026-0001.
     *
     * Đọc số lớn nhất của năm rồi cộng một. Cả hàm này chạy trong giao dịch của `issue()`, và
     * `contract_number` có chỉ mục duy nhất — nên hai yêu cầu cùng lúc thì một cái phải chờ, còn
     * nếu có lọt qua thì cơ sở dữ liệu chặn ở lớp cuối chứ không cấp trùng số.
     */
    private function soTiepTheo(): string
    {
        $nam = GioVietNam::bayGio()->year;
        $tienTo = sprintf('HD-%d-', $nam);

        $soCuoi = BookingContract::query()
            ->where('contract_number', 'like', $tienTo . '%')
            ->lockForUpdate()
            ->orderByDesc('contract_number')
            ->value('contract_number');

        $ke = $soCuoi ? ((int) substr($soCuoi, strlen($tienTo))) + 1 : 1;

        return $tienTo . str_pad((string) $ke, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Đơn nào cấp được hợp đồng.
     *
     * Đơn đang giữ chỗ chưa phải giao dịch: khách chưa trả tiền, chỗ có thể bị nhả bất cứ lúc
     * nào. In hợp đồng cho nó là đưa khách một văn bản nói rằng hai bên đã thỏa thuận, trong khi
     * chưa bên nào cam kết gì.
     */
    private function assertCoTheCapHopDong(Booking $booking): void
    {
        $trangThai = $booking->status instanceof BookingStatus
            ? $booking->status->value
            : (string) $booking->status;

        if (in_array($trangThai, ['pending', 'cancelled', 'transferred'], true)) {
            throw new BusinessRuleException(
                'Chỉ đơn đã xác nhận mới cấp được hợp đồng. Đơn đang giữ chỗ hoặc đã hủy thì '
                    . 'chưa có giao dịch nào để ký.',
            );
        }
    }
}
