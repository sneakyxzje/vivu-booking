<?php

namespace App\Services\Sandbox;

/**
 * Một bước trong biên bản chạy kịch bản.
 *
 * Bốn phần, và phần thứ tư là thứ biến biên bản thành bằng chứng thay vì một dòng nhật ký:
 *
 *   - `lamGi`   — thao tác vừa thực hiện, nói bằng tiếng của nghiệp vụ chứ không phải tên hàm.
 *   - `kyVong`  — điều lẽ ra phải xảy ra, viết TRƯỚC khi chạy.
 *   - `ketQua`  — điều thật sự đã xảy ra, đọc lại từ cơ sở dữ liệu.
 *   - `dat`     — hai cái trên có khớp không.
 *
 * Không có `dat` thì người xem phải tự đối chiếu hai đoạn chữ và tự kết luận — đúng thứ mà bảng
 * nút bấm cũ bắt họ làm. Có nó thì một kịch bản hỏng lộ ra ngay ở đúng bước hỏng.
 */
class SandboxStep
{
    public function __construct(
        public readonly int $thuTu,
        public readonly string $lamGi,
        public readonly string $kyVong,
        public readonly string $ketQua,
        public readonly bool $dat,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'thu_tu' => $this->thuTu,
            'lam_gi' => $this->lamGi,
            'ky_vong' => $this->kyVong,
            'ket_qua' => $this->ketQua,
            'dat' => $this->dat,
        ];
    }
}
