<?php

namespace App\Enums;

/**
 * Vì sao đơn này bị chuyển sang chuyến khác.
 *
 * Trước đây chỉ có một ô ghi chú tự do. Ô tự do không sai, nhưng nó đặt mọi lý do ngang hàng nhau:
 * "khách bận công tác" và "bão số 9 cấm biển" trông giống hệt nhau trong bảng, và không có gì trên
 * màn hình nhắc rằng chuyển chuyến là việc phải có căn cứ chứ không phải quyền tùy nghi của điều
 * hành.
 *
 * Bốn nhóm dưới đây là bốn loại căn cứ thật. Ô ghi chú vẫn còn và vẫn bắt buộc — nhóm nói *loại*
 * căn cứ, ghi chú nói *việc gì đã xảy ra*.
 */
enum TransferReasonCategory: string
{
    /** Thiên tai, thời tiết cực đoan: bão, lũ, sạt lở, cấm biển. */
    case ForceMajeure = 'force_majeure';

    /** Quyết định của cơ quan nhà nước: cấm đường, đóng cửa khẩu, dừng lễ hội, dịch bệnh. */
    case Authority = 'authority';

    /** Nhà cung cấp hỏng việc: xe hỏng, khách sạn hủy phòng, hãng bay đổi giờ. */
    case Supplier = 'supplier';

    /** Khách xin đổi vì việc riêng. Loại duy nhất công ty được phép thu phí đổi lịch. */
    case CustomerRequest = 'customer_request';

    public function label(): string
    {
        return match ($this) {
            self::ForceMajeure => 'Thiên tai, thời tiết',
            self::Authority => 'Quyết định của cơ quan nhà nước',
            self::Supplier => 'Nhà cung cấp không thực hiện được',
            self::CustomerRequest => 'Khách xin đổi vì việc riêng',
        };
    }

    /**
     * Ba nhóm đầu là bất khả kháng với cả hai bên, nên công ty chịu, không thu phí đổi lịch của
     * khách. Nhóm cuối là việc riêng của khách nên áp quy tắc phí như cũ.
     */
    public function batKhaKhang(): bool
    {
        return $this !== self::CustomerRequest;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
