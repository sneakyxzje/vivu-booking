<?php

namespace App\Traits;

use Illuminate\Support\Carbon;

/**
 * Đọc hai đầu của một khoảng thời gian gửi từ bộ lọc.
 *
 * ## Vấn đề nó giải
 *
 * Giao diện dùng chung một bộ chọn khoảng thời gian, và bộ chọn ấy gửi lên hai dạng tùy màn hình:
 *
 *   - `2026-08-30`        — màn chỉ lọc theo ngày
 *   - `2026-08-30T14:00`  — màn có cho chọn giờ
 *
 * Máy chủ phải hiểu cả hai. Dùng `whereDate()` thì phần giờ bị cắt bỏ, tức người dùng chỉnh giờ
 * xong mà kết quả không đổi — giao diện hứa một thứ máy chủ không làm. Ngược lại, so thẳng bằng
 * `where('cot', '>=', $gia_tri)` với một chuỗi chỉ có ngày thì mốc cuối rơi vào 0 giờ, và cả ngày
 * cuối cùng của khoảng biến mất khỏi kết quả.
 *
 * Hai hàm dưới đây nhận diện bằng độ dài chuỗi, không bằng một tham số riêng: nơi gọi không cần
 * biết màn hình nào đang gửi dạng nào.
 */
trait LocKhoangThoiGian
{
    /** Mốc đầu khoảng. Không khai giờ thì lấy từ 0 giờ của ngày đó. */
    protected function mocDau(string $giaTri): Carbon
    {
        $moc = Carbon::parse($giaTri);

        return strlen(trim($giaTri)) <= 10 ? $moc->startOfDay() : $moc;
    }

    /** Mốc cuối khoảng. Không khai giờ thì lấy tới hết ngày, nếu không sẽ hụt mất chính ngày đó. */
    protected function mocCuoi(string $giaTri): Carbon
    {
        $moc = Carbon::parse($giaTri);

        return strlen(trim($giaTri)) <= 10 ? $moc->endOfDay() : $moc;
    }
}
