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
 * Xóa tour — luôn là xóa mềm, và chặn khi đoàn còn đang đi.
 *
 * ## Vì sao xóa mềm chứ không xóa cứng
 *
 * Đơn hàng, đánh giá và yêu cầu đoàn đều trỏ tới tour. Xóa cứng là xóa theo cả chứng từ tài
 * chính của khách - hành khách, sổ giao dịch, nhật ký thanh toán. Với một hệ thống có dòng tiền
 * thì đó là thứ không được phép mất, kể cả khi người bấm thật sự muốn.
 *
 * Nên "xóa" ở đây nghĩa là **cất đi**: tour biến mất khỏi mọi danh sách của khách lẫn của điều
 * hành, không đặt được nữa, nhưng hàng dữ liệu còn nguyên và đơn cũ vẫn tra ra được tên tour.
 * Bấm nhầm thì khôi phục lại được.
 *
 * Cơ sở dữ liệu cũng đã được đổi để không cho xóa cứng nữa: ba khóa ngoại mang chứng từ chuyển
 * từ `cascade` sang `restrict` ở migration `2026_08_17_000011`. Lớp này là hàng rào thứ nhất,
 * khóa ngoại là hàng rào thứ hai - chứng từ không nên phụ thuộc vào việc ai cũng nhớ gọi đúng
 * lớp dịch vụ.
 *
 * ## Điều kiện chặn duy nhất
 *
 * **Đoàn đang trên đường thì không cất tour đi được.** Chuyến đã chốt hoặc đang khởi hành nghĩa
 * là có khách đã trả tiền và đang đi; điều hành cần thấy tour ấy để điểm danh, xử lý sự cố, bàn
 * giao hướng dẫn viên. Cất nó khỏi danh sách giữa chừng là lấy mất công cụ vận hành.
 *
 * Ngoài ra không chặn gì. Tour có bao nhiêu đơn cũ cũng cất đi được, vì cất không làm mất gì.
 *
 * ## Cất đi khác ngừng bán
 *
 * **Ngừng bán** giữ tour trong màn quản trị, chỉ thôi nhận khách mới - dùng khi tour tạm dừng
 * theo mùa hoặc chờ cập nhật giá. **Cất đi** thì biến mất khỏi cả màn quản trị - dùng khi tour
 * không còn bán nữa và không muốn nó chiếm chỗ trong danh sách.
 */
class TourDeletionService
{
    /** Hai trạng thái nghĩa là có khách đã trả tiền và đang trông vào tour này. */
    private const DANG_CHAY = [
        ScheduleStatus::Confirmed->value,
        ScheduleStatus::InProgress->value,
    ];

    /**
     * Lý do chưa cất tour đi được, hoặc mảng rỗng nếu cất được.
     *
     * @return array<int, array{key: string, count: int, message: string}>
     */
    public function blockers(Tour $tour): array
    {
        $soChuyenDangChay = TourSchedule::query()
            ->where('tour_id', $tour->getKey())
            ->whereIn('status', self::DANG_CHAY)
            ->count();

        if ($soChuyenDangChay === 0) {
            return [];
        }

        return [[
            'key' => 'running_schedules',
            'count' => $soChuyenDangChay,
            'message' => sprintf(
                '%d chuyến đã chốt hoặc đang khởi hành. Khách đã trả tiền và đang trông vào '
                . 'chuyến đó, còn điều hành vẫn cần tour này để điểm danh, xử lý sự cố và bàn '
                . 'giao hướng dẫn viên. Đợi chuyến kết thúc rồi hãy cất tour đi.',
                $soChuyenDangChay,
            ),
        ]];
    }

    /**
     * Xem trước: cất đi được không, và những gì vẫn ở lại.
     *
     * Phần "vẫn ở lại" quan trọng ngang phần chặn - người bấm cần biết mình không làm mất gì.
     *
     * @return array<string, mixed>
     */
    public function preview(Tour $tour): array
    {
        return [
            'tour_id' => $tour->getKey(),
            'tour_title' => $tour->title,
            'can_delete' => $this->blockers($tour) === [],
            'blockers' => $this->blockers($tour),
            // Những thứ KHÔNG mất đi. Cất tour không đụng tới bất cứ dòng nào ở đây.
            'preserved' => [
                'bookings' => Booking::query()->where('tour_id', $tour->getKey())->count(),
                'reviews' => Review::query()->where('tour_id', $tour->getKey())->count(),
                'group_requests' => GroupBookingRequest::query()->where('tour_id', $tour->getKey())->count(),
                'schedules' => TourSchedule::query()->where('tour_id', $tour->getKey())->count(),
            ],
            'already_retired' => $tour->status !== 'active',
        ];
    }

    /**
     * Cất tour đi. Hoàn tác được bằng `restore()`.
     */
    public function delete(Tour $tour): void
    {
        DB::transaction(function () use ($tour) {
            // Khóa rồi đọc lại: một chuyến có thể vừa được chốt trong lúc điều hành còn đang
            // nhìn hộp thoại xem trước.
            $tuoi = Tour::query()->whereKey($tour->getKey())->lockForUpdate()->first();

            if (! $tuoi) {
                throw new BusinessRuleException('Tour này đã được cất đi trước đó.');
            }

            $canTro = $this->blockers($tuoi);

            if ($canTro !== []) {
                throw new BusinessRuleException($canTro[0]['message']);
            }

            $tuoi->delete();
        });
    }

    /** Lấy lại tour đã cất. */
    public function restore(int $tourId): Tour
    {
        $tour = Tour::withTrashed()->find($tourId);

        if (! $tour) {
            throw new BusinessRuleException('Không tìm thấy tour này.');
        }

        if (! $tour->trashed()) {
            throw new BusinessRuleException('Tour này đang hiển thị bình thường, không cần khôi phục.');
        }

        $tour->restore();

        return $tour;
    }

    /**
     * Ngừng bán — nhẹ hơn cất đi: tour vẫn nằm trong màn quản trị, chỉ thôi nhận khách mới.
     *
     * Không đụng tới chuyến đã chốt: khách đã mua thì chuyến vẫn phải chạy đúng cam kết.
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
