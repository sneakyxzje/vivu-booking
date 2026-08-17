<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sửa thông tin liên hệ của người đặt: tên, điện thoại, thư điện tử.
 *
 * Đây là chỗ khách gõ nhầm nhiều nhất, và trước đây không có đường nào sửa. Gõ sai một chữ số
 * điện thoại lúc đặt là hướng dẫn viên không gọi được vào sáng khởi hành.
 *
 * **Cố ý KHÔNG áp hạn chốt danh sách.** Đây là điểm khác căn bản so với danh sách hành khách:
 *
 *   - Danh sách hành khách gửi cho nhà cung cấp để mua bảo hiểm, xuất vé, khai báo lưu trú. Sau
 *     hạn chốt danh sách ấy đã ra khỏi tay công ty, nên khách không tự sửa được nữa.
 *   - Thông tin liên hệ không đi đâu cả. Nó là số mà công ty và hướng dẫn viên gọi khách. Càng
 *     sát ngày khởi hành thì càng cần đúng, nên khóa nó theo hạn chốt là khóa ngược.
 *
 * Vì thế sửa được cả sau hạn chốt và cả khi đoàn đang đi. Chỉ dừng khi đơn đã kết thúc vòng đời:
 * lúc đó sửa cũng không để làm gì, mà lại làm sai dữ liệu đối chiếu về sau.
 *
 * Số lượng khách thì không sửa được, và đó là quyết định có chủ ý chứ không phải thiếu sót: đổi
 * số người là đổi cả chỗ lẫn tiền, khác hẳn việc sửa một chữ số gõ nhầm. Khách cần đổi số người
 * thì hủy và đặt lại theo đúng chính sách hủy.
 */
class BookingContactService
{
    public function __construct(
        private readonly BookingAuditLogger $auditLogger,
    ) {
    }

    /**
     * Ai sửa được thông tin liên hệ của đơn này.
     *
     * @return array{customer: bool, admin: bool, reason: string|null}
     */
    public function editability(Booking $booking): array
    {
        $trangThai = BookingStatus::tryFrom((string) $booking->status);

        if ($trangThai?->isTerminal()) {
            return [
                'customer' => false,
                'admin' => false,
                'reason' => sprintf(
                    'Đơn đang ở trạng thái "%s" nên không sửa thông tin liên hệ được nữa.',
                    $trangThai->label(),
                ),
            ];
        }

        return ['customer' => true, 'admin' => true, 'reason' => null];
    }

    public function assertEditable(Booking $booking): void
    {
        $quyen = $this->editability($booking);

        if (!$quyen['admin']) {
            throw new BusinessRuleException($quyen['reason'] ?? 'Không sửa được thông tin liên hệ.');
        }
    }

    /**
     * Ghi thông tin liên hệ mới.
     *
     * @param  array{customer_name: string, customer_email: string, customer_phone?: string|null}  $moi
     */
    public function update(Booking $booking, array $moi, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($booking, $moi, $actor) {
            $khoa = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            if (!$khoa) {
                throw new BusinessRuleException('Không tìm thấy đơn đặt tour.', 404);
            }

            $this->assertEditable($khoa);

            $cu = [
                'customer_name' => $khoa->customer_name,
                'customer_email' => $khoa->customer_email,
                'customer_phone' => $khoa->customer_phone,
            ];

            $sau = [
                'customer_name' => trim($moi['customer_name']),
                'customer_email' => trim($moi['customer_email']),
                'customer_phone' => isset($moi['customer_phone']) ? trim((string) $moi['customer_phone']) : null,
            ];

            // Không đổi gì thì không ghi một dòng nhật ký rỗng: màn sửa gửi lại cả ba trường mỗi
            // lần lưu, nên phần lớn lần gọi tới đây không có thay đổi thật.
            $thayDoi = array_keys(array_filter(
                $sau,
                fn ($giaTri, $khoaTruong) => $giaTri !== $cu[$khoaTruong],
                ARRAY_FILTER_USE_BOTH,
            ));

            if ($thayDoi === []) {
                return $khoa;
            }

            $khoa->forceFill($sau)->save();

            $this->auditLogger->log(
                $khoa,
                BookingAuditAction::ContactUpdated,
                // Chỉ ghi trường thật sự đổi, để người đọc nhật ký thấy ngay cái gì vừa thay.
                array_intersect_key($cu, array_flip($thayDoi)),
                array_intersect_key($sau, array_flip($thayDoi)),
                $actor ? null : 'Khách tự sửa từ trang đơn của mình.',
            );

            return $khoa->fresh();
        });
    }

    /** @return array<string, array<int, string>> */
    public static function validationRules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
