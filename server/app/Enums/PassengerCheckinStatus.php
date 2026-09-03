<?php

namespace App\Enums;

/**
 * Trạng thái điểm danh của một hành khách tại một điểm dừng.
 *
 * Định nghĩa ở docs/nghiep-vu/04-luong-dieu-hanh.md mục 5.2.
 *
 * Vì sao không dùng boolean có mặt hay vắng: thực tế đoàn có nhiều tình huống khác nhau và
 * mỗi tình huống dẫn tới một hành động khác nhau. Khách báo trước không tham gia một hoạt động
 * khác hẳn với khách không liên lạc được, dù cả hai đều là "không có mặt".
 */
enum PassengerCheckinStatus: string
{
    /** Có mặt bình thường. */
    case Present = 'present';

    /** Không có mặt tại điểm dừng này. */
    case Absent = 'absent';

    /** Tới muộn nhưng vẫn tham gia. */
    case Late = 'late';

    /** Đã rời đoàn về sớm, không tham gia các điểm dừng còn lại. */
    case LeftEarly = 'left_early';

    /** Vắng có báo trước, đã thống nhất với hướng dẫn viên. */
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Có mặt',
            self::Absent => 'Vắng mặt',
            self::Late => 'Đến muộn',
            self::LeftEarly => 'Rời đoàn sớm',
            self::Excused => 'Vắng có phép',
        };
    }

    /**
     * Trạng thái nào bắt buộc phải kèm ghi chú.
     *
     * Yêu cầu trực tiếp của hội đồng. Lý do nghiệp vụ: mọi trạng thái khác có mặt đều có thể
     * dẫn tới khiếu nại hoặc yêu cầu hoàn tiền về sau, mà lúc đó không ai nhớ chuyện gì đã xảy ra
     * nếu không ghi lại ngay tại chỗ.
     */
    public function requiresNote(): bool
    {
        return $this !== self::Present;
    }

    /*
     * ĐÃ GỠ: `endsParticipation()`.
     *
     * Nó trả lời "khách đã rời đoàn chưa", để mở luồng xét hoàn phần dịch vụ chưa dùng. Luồng ấy
     * chưa được xây, và không dòng nào gọi hàm này.
     *
     * Một hàm mang tên như một luật, nằm cạnh những luật đang có hiệu lực thật, nhưng không ai gọi
     * — đó chính là cái bẫy đã khiến luật "điểm dừng bắt buộc ảnh" nằm chết mà vẫn có bài kiểm thử
     * xanh: người đọc mã tin rằng hệ thống đang thực thi nó. Ý định vẫn còn trong
     * docs/nghiep-vu/04-luong-dieu-hanh.md; dựng lại hàm khi thật sự có luồng gọi nó.
     */

    /**
     * Trạng thái cần báo ngay cho điều hành thay vì chỉ ghi vào báo cáo cuối chuyến.
     * Khách vắng mà không rõ lý do là tình huống có thể phải xử lý gấp.
     */
    public function needsOperatorAlert(): bool
    {
        return $this === self::Absent;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
