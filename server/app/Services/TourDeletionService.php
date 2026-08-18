<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\GroupBookingRequest;
use App\Models\Review;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Xóa tour — và chặn xóa khi tour đã có lịch sử.
 *
 * ## Vì sao phải có lớp này thay vì gọi thẳng `$tour->delete()`
 *
 * `bookings.tour_id` khai `onDelete('cascade')`. Gọi xóa thẳng thì cơ sở dữ liệu **im lặng xóa
 * theo mọi đơn hàng của tour** - kèm hành khách, sổ giao dịch, nhật ký thay đổi, nhật ký cổng
 * thanh toán. Không có cảnh báo, không có cách hoàn tác. Cùng đường ấy còn kéo theo đánh giá của
 * khách và các yêu cầu đoàn.
 *
 * Nói cách khác: hàng rào duy nhất giữa một cú bấm nhầm và toàn bộ dữ liệu tài chính của một
 * tour chính là lớp này.
 *
 * ## Ranh giới
 *
 * **Xóa cứng chỉ dành cho tour chưa từng được dùng** - tạo nhầm, tạo thử, tạo xong đổi ý. Đây
 * đúng là tình huống thật cần nút xóa.
 *
 * Tour đã phát sinh bất cứ dấu vết nào thì **không xóa**, mà chuyển sang **ngừng bán**: nó biến
 * mất khỏi trang khách, không đặt được nữa, nhưng mọi đơn cũ và các chuyến đang chạy vẫn nguyên
 * vẹn. Đây là câu trả lời cho góp ý số 15 của hội đồng.
 *
 * Hệ thống **không tự chuyển sang ngừng bán** khi thấy không xóa được. Người dùng bấm "xóa" mà
 * hệ thống lặng lẽ làm việc khác là hệ thống quyết thay họ; ở đây nó báo rõ vì sao không xóa
 * được, rồi để họ chọn.
 */
class TourDeletionService
{
    /**
     * Chuyến ở hai trạng thái này là chưa rời khỏi quầy: chưa chốt, chưa chạy, chưa hủy.
     * Ngoài hai trạng thái đó thì chuyến đã có đời sống vận hành, xóa đi là xóa lịch sử.
     */
    private const CHUA_DUNG_TOI = [
        ScheduleStatus::Open->value,
        ScheduleStatus::Closed->value,
    ];

    /**
     * Những gì đang chặn việc xóa, kèm số lượng cụ thể.
     *
     * Trả về mảng rỗng nghĩa là xóa được. Chặn mà không nói rõ bao nhiêu cái đang chặn thì người
     * dùng không biết phải đi dọn cái gì - cùng nguyên tắc với màn hủy chuyến.
     *
     * @return array<int, array{key: string, count: int, message: string}>
     */
    public function blockers(Tour $tour): array
    {
        $canTro = [];

        $soDon = Booking::query()->where('tour_id', $tour->getKey())->count();

        if ($soDon > 0) {
            $canTro[] = [
                'key' => 'bookings',
                'count' => $soDon,
                'message' => sprintf(
                    '%d đơn đặt tour đã phát sinh. Đơn hàng là chứng từ tài chính, xóa tour là '
                    . 'xóa theo cả hành khách, sổ tiền và nhật ký của chúng.',
                    $soDon,
                ),
            ];
        }

        $soChuyenDaDung = TourSchedule::query()
            ->where('tour_id', $tour->getKey())
            ->whereNotIn('status', self::CHUA_DUNG_TOI)
            ->count();

        if ($soChuyenDaDung > 0) {
            $canTro[] = [
                'key' => 'schedules',
                'count' => $soChuyenDaDung,
                'message' => sprintf(
                    '%d chuyến đã chốt, đã chạy, đã kết thúc hoặc đã hủy. Đó là lịch sử vận hành, '
                    . 'kèm phân công hướng dẫn viên và sự cố dọc đường.',
                    $soChuyenDaDung,
                ),
            ];
        }

        $soYeuCauDoan = GroupBookingRequest::query()->where('tour_id', $tour->getKey())->count();

        if ($soYeuCauDoan > 0) {
            $canTro[] = [
                'key' => 'group_requests',
                'count' => $soYeuCauDoan,
                'message' => sprintf(
                    '%d yêu cầu booking đoàn đang trỏ tới tour này, gồm cả báo giá đã gửi cho khách.',
                    $soYeuCauDoan,
                ),
            ];
        }

        $soDanhGia = Review::query()->where('tour_id', $tour->getKey())->count();

        if ($soDanhGia > 0) {
            $canTro[] = [
                'key' => 'reviews',
                'count' => $soDanhGia,
                'message' => sprintf('%d đánh giá của khách. Đây là tiếng nói của khách, không xóa hộ họ.', $soDanhGia),
            ];
        }

        return $canTro;
    }

    /**
     * Xem trước: xóa được không, nếu không thì vì sao, nếu được thì mất những gì.
     *
     * @return array<string, mixed>
     */
    public function preview(Tour $tour): array
    {
        $canTro = $this->blockers($tour);

        return [
            'tour_id' => $tour->getKey(),
            'tour_title' => $tour->title,
            'can_delete' => $canTro === [],
            'blockers' => $canTro,
            // Chỉ có nghĩa khi xóa được: những thứ sẽ mất theo, để người bấm biết trước.
            'cascades' => [
                'schedules' => TourSchedule::query()->where('tour_id', $tour->getKey())->count(),
                'itineraries' => $tour->itineraries()->count(),
                'images' => $tour->images()->count(),
            ],
            'already_retired' => $tour->status !== 'active',
        ];
    }

    /**
     * Xóa cứng. Ném lỗi kèm đầy đủ lý do nếu tour đã có lịch sử.
     */
    public function delete(Tour $tour): void
    {
        DB::transaction(function () use ($tour) {
            // Khóa rồi đọc lại: một đơn vừa được tạo trong lúc điều hành còn đang nhìn hộp thoại
            // xem trước thì lần kiểm này phải thấy nó, không phải bản chụp lúc mở hộp thoại.
            $tuoi = Tour::query()->whereKey($tour->getKey())->lockForUpdate()->first();

            if (!$tuoi) {
                throw new BusinessRuleException('Tour này đã bị xóa trước đó.');
            }

            $canTro = $this->blockers($tuoi);

            if ($canTro !== []) {
                throw new BusinessRuleException(
                    'Không xóa được tour này vì đã phát sinh dữ liệu: '
                    . implode(' ', array_column($canTro, 'message'))
                    . ' Hãy chuyển tour sang ngừng bán - tour sẽ biến mất khỏi trang khách và '
                    . 'không đặt được nữa, nhưng dữ liệu cũ vẫn còn nguyên.',
                );
            }

            $tuoi->delete();
        });
    }

    /**
     * Ngừng bán: lối đi an toàn cho tour đã có lịch sử.
     *
     * Không đụng tới chuyến nào đang chạy - chuyến đã chốt vẫn phải chạy đúng cam kết với khách
     * đã mua. Ngừng bán chỉ có nghĩa là **không nhận khách mới**.
     */
    public function retire(Tour $tour, ?User $actor = null): Tour
    {
        if ($tour->status !== 'active') {
            throw new BusinessRuleException('Tour này vốn đã ngừng bán.');
        }

        $tour->forceFill(['status' => 'inactive'])->save();

        return $tour;
    }
}
