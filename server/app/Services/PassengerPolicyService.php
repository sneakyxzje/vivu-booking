<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\TourSchedule;
use Illuminate\Support\Carbon;

/**
 * G02, G03 - Quy tắc thông tin hành khách và quyền sửa theo mốc thời gian.
 *
 * Câu số 3 của hội đồng. Định nghĩa ở docs/nghiep-vu/02-luong-dat-tour.md mục 3.1.
 *
 * Điểm đáng chú ý của nhóm này: quyền sửa không phụ thuộc vai trò, mà phụ thuộc **thời điểm**.
 * Cùng một người, cùng một đơn, hôm nay sửa được và ngày mai thì không - vì hạn chốt danh sách
 * đã qua và danh sách đã gửi cho nhà cung cấp. Đây là lần thứ ba booking_deadline điều khiển
 * một luật hoàn toàn khác: đặt chỗ (nhóm A), trả chỗ (nhóm C), và giờ là sửa tên.
 */
class PassengerPolicyService
{
    /** Từ mốc này tính là người lớn. */
    public const ADULT_FROM_AGE = 12;

    /** Dưới mốc này tính là em bé, không chiếm ghế riêng. */
    public const INFANT_UNDER_AGE = 2;

    public function __construct(
        private ScheduleLifecycleService $lifecycle,
    ) {
    }

    /**
     * Ai còn sửa được danh sách hành khách của đơn này, tại thời điểm hiện tại.
     *
     * @return array{customer: bool, admin: bool, reason: string|null}
     */
    public function editability(Booking $booking, ?TourSchedule $schedule = null, ?Carbon $now = null): array
    {
        $schedule ??= $booking->schedule;
        $now ??= now();

        if (!$schedule) {
            // Đơn không gắn chuyến thì không có mốc thời gian nào để dựa vào, cứ cho sửa.
            return ['customer' => true, 'admin' => true, 'reason' => null];
        }

        $trangThai = $this->lifecycle->effectiveStatus($schedule, $now);

        // Đoàn đã lên đường thì danh sách là dữ liệu lịch sử. Sửa tên lúc này nghĩa là sửa
        // chính thứ đang dùng để điểm danh và đối chiếu khi có sự cố.
        if ($trangThai->isRunning() || $trangThai->isFinal()) {
            return [
                'customer' => false,
                'admin' => false,
                'reason' => sprintf(
                    'Chuyến đang ở trạng thái "%s" nên không sửa được danh sách hành khách nữa, chỉ ghi chú bổ sung.',
                    $trangThai->label(),
                ),
            ];
        }

        $hanChot = $schedule->booking_deadline ?? $schedule->defaultBookingDeadline();

        if ($hanChot && $now->gte($hanChot)) {
            return [
                'customer' => false,
                'admin' => true,
                'reason' => 'Đã qua hạn chốt danh sách nên danh sách đã gửi nhà cung cấp. '
                    . 'Vui lòng liên hệ bộ phận điều hành để sửa.',
            ];
        }

        return ['customer' => true, 'admin' => true, 'reason' => null];
    }

    public function assertCustomerCanEdit(Booking $booking, ?TourSchedule $schedule = null): void
    {
        $quyen = $this->editability($booking, $schedule);

        if (!$quyen['customer']) {
            throw new BusinessRuleException($quyen['reason'] ?? 'Không sửa được danh sách hành khách.');
        }
    }

    public function assertAdminCanEdit(Booking $booking, ?TourSchedule $schedule = null): void
    {
        $quyen = $this->editability($booking, $schedule);

        if (!$quyen['admin']) {
            throw new BusinessRuleException($quyen['reason'] ?? 'Không sửa được danh sách hành khách.');
        }
    }

    /**
     * Kiểm tra cả danh sách trước khi ghi.
     *
     * Kiểm theo lô chứ không theo từng người, vì luật trùng số giấy tờ chỉ nhìn thấy được khi
     * xét cả đơn cùng lúc.
     *
     * @param  array<int, array<string, mixed>>  $passengers
     */
    public function validateList(Booking $booking, array $passengers): void
    {
        $this->assertKhongTrungGiayTo($passengers);
        $this->assertTuoiKhopPhanLoai($booking, $passengers);
    }

    /**
     * Số giấy tờ trùng nhau trong cùng một đơn thì từ chối.
     *
     * Gần như luôn là gõ nhầm: chép dòng trên xuống rồi quên sửa số. Để lọt thì danh sách gửi
     * khách sạn có hai người cùng số căn cước, và lễ tân sẽ gọi lại hỏi.
     *
     * @param  array<int, array<string, mixed>>  $passengers
     */
    private function assertKhongTrungGiayTo(array $passengers): void
    {
        $daGap = [];

        foreach ($passengers as $index => $passenger) {
            $so = trim((string) ($passenger['identity_number'] ?? ''));

            if ($so === '') {
                continue;
            }

            $khoa = mb_strtolower($so);

            if (isset($daGap[$khoa])) {
                throw new BusinessRuleException(sprintf(
                    'Số giấy tờ "%s" bị trùng giữa hành khách thứ %d và thứ %d trong cùng một đơn.',
                    $so,
                    $daGap[$khoa] + 1,
                    $index + 1,
                ));
            }

            $daGap[$khoa] = $index;
        }
    }

    /**
     * Ngày sinh phải khớp với loại khách đã đặt.
     *
     * Loại khách quyết định giá vé, nên khai một người lớn thành trẻ em là trả thiếu tiền. Chỉ
     * kiểm khi có ngày sinh; trường này không bắt buộc vì nhiều khách đặt hộ và chưa hỏi kịp.
     *
     * @param  array<int, array<string, mixed>>  $passengers
     */
    private function assertTuoiKhopPhanLoai(Booking $booking, array $passengers): void
    {
        $ngayDi = $booking->departure_date ?? $booking->schedule?->start_date;

        if (!$ngayDi) {
            return;
        }

        foreach ($passengers as $index => $passenger) {
            $ngaySinh = $passenger['date_of_birth'] ?? null;

            if (!$ngaySinh) {
                continue;
            }

            $tuoi = Carbon::parse($ngaySinh)->diffInYears(Carbon::parse($ngayDi));
            $loaiDung = $this->loaiTheoTuoi($tuoi);
            $loaiKhai = $passenger['type'] ?? null;

            if ($loaiKhai !== null && $loaiKhai !== $loaiDung) {
                throw new BusinessRuleException(sprintf(
                    'Hành khách thứ %d sinh ngày %s, tới ngày khởi hành là %d tuổi nên thuộc loại "%s", không phải "%s".',
                    $index + 1,
                    Carbon::parse($ngaySinh)->format('d/m/Y'),
                    $tuoi,
                    $this->nhanLoai($loaiDung),
                    $this->nhanLoai($loaiKhai),
                ));
            }
        }
    }

    public function loaiTheoTuoi(int $tuoi): string
    {
        if ($tuoi < self::INFANT_UNDER_AGE) {
            return 'infant';
        }

        return $tuoi < self::ADULT_FROM_AGE ? 'child' : 'adult';
    }

    private function nhanLoai(string $loai): string
    {
        return match ($loai) {
            'adult' => 'người lớn',
            'child' => 'trẻ em',
            'infant' => 'em bé',
            default => $loai,
        };
    }

    /**
     * G05 - Cảnh báo khi danh sách khai chưa đủ so với số khách đã đặt.
     *
     * Không chặn việc lưu, vì khách thường đặt trước rồi mới hỏi đủ thông tin của cả nhà. Nhưng
     * phải hiện rõ, và theo tài liệu 02 mục 3.1 thì chặn xuất danh sách đoàn cho tới khi khai đủ.
     *
     * @return array<int, string>
     */
    public function manifestWarnings(Booking $booking): array
    {
        $canhBao = [];

        $daKhai = $booking->passengers()->count();
        $daDat = (int) $booking->guests;

        if ($daKhai < $daDat) {
            $canhBao[] = sprintf(
                'Mới khai %d trên %d hành khách. Chưa xuất được danh sách đoàn cho tới khi khai đủ.',
                $daKhai,
                $daDat,
            );
        }

        $thieuGiayTo = $booking->passengers()
            ->whereNull('identity_number')
            ->orWhere(function ($query) use ($booking) {
                $query->where('booking_id', $booking->getKey())->where('identity_number', '');
            })
            ->count();

        if ($thieuGiayTo > 0) {
            $canhBao[] = sprintf(
                '%d hành khách chưa có số giấy tờ, khách sạn cần thông tin này để khai báo lưu trú.',
                $thieuGiayTo,
            );
        }

        if ($daKhai > 0 && $booking->passengers()->where('is_contact', true)->doesntExist()) {
            $canhBao[] = 'Chưa chọn người liên hệ của đoàn, hướng dẫn viên sẽ không biết gọi ai.';
        }

        return $canhBao;
    }

    /**
     * Ghi đè danh sách hành khách của một đơn.
     *
     * Xóa rồi tạo lại thay vì so khớp từng dòng: danh sách hành khách không có khóa nghiệp vụ
     * ổn định nào để so, và mọi thứ tham chiếu tới hành khách (điểm danh) chỉ tồn tại sau khi
     * chuyến khởi hành, tức sau khi danh sách đã khóa.
     *
     * @param  array<int, array<string, mixed>>  $passengers
     */
    public function replaceList(Booking $booking, array $passengers): void
    {
        $booking->passengers()->delete();

        foreach ($passengers as $passenger) {
            $booking->passengers()->create([
                'name' => $passenger['name'],
                'gender' => $passenger['gender'] ?? null,
                'type' => $passenger['type'],
                'date_of_birth' => $passenger['date_of_birth'] ?? null,
                'identity_number' => $passenger['identity_number'] ?? null,
                'id_type' => $passenger['id_type'] ?? null,
                'nationality' => $passenger['nationality'] ?? null,
                'phone' => $passenger['phone'] ?? null,
                'special_request' => $passenger['special_request'] ?? null,
                'is_contact' => (bool) ($passenger['is_contact'] ?? false),
                'note' => $passenger['note'] ?? null,
            ]);
        }
    }

    /** @return array<string, mixed> */
    public static function validationRules(string $prefix = 'passengers'): array
    {
        return [
            $prefix => ['required', 'array', 'min:1', 'max:50'],
            "{$prefix}.*.name" => ['required', 'string', 'max:255'],
            "{$prefix}.*.type" => ['required', 'in:adult,child,infant'],
            "{$prefix}.*.gender" => ['nullable', 'in:male,female,other'],
            "{$prefix}.*.date_of_birth" => ['nullable', 'date', 'before_or_equal:today'],
            "{$prefix}.*.identity_number" => ['nullable', 'string', 'max:50'],
            "{$prefix}.*.id_type" => ['nullable', 'in:cccd,cmnd,passport,birth_certificate'],
            "{$prefix}.*.nationality" => ['nullable', 'string', 'max:60'],
            "{$prefix}.*.phone" => ['nullable', 'string', 'max:20'],
            "{$prefix}.*.special_request" => ['nullable', 'string', 'max:500'],
            "{$prefix}.*.is_contact" => ['nullable', 'boolean'],
            "{$prefix}.*.note" => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Số hành khách chưa khai của một đơn, dùng cho báo cáo tổng hợp. */
    public function missingCount(Booking $booking): int
    {
        return max(0, (int) $booking->guests - $booking->passengers()->count());
    }

    /** @return \Illuminate\Support\Collection<int, BookingPassenger> */
    public function contactsOf(Booking $booking)
    {
        return $booking->passengers()->where('is_contact', true)->get();
    }
}
