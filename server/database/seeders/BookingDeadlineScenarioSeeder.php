<?php

namespace Database\Seeders;

use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\CancellationPolicy;
use App\Models\GroupBookingRequest;
use App\Models\Review;
use App\Models\ScheduleAuditLog;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Phòng thí nghiệm cho một luồng duy nhất: hạn chốt danh sách khách.
 *
 * Khác BusinessScenarioSeeder ở chỗ hẹp hơn hẳn. Seeder kia dựng chín chuyến phủ cả vòng đời; ở
 * đây chỉ có hai chuyến và ba đơn, vì câu hỏi cần trả lời cũng chỉ có một: *dịch cái mốc hạn chốt
 * thì chuyện gì xảy ra, và có xảy ra giống nhau cho mọi khách không.*
 *
 * ## Vì sao ba đơn khác nhau đúng ở mỗi thời điểm đặt
 *
 * Hệ thống chỉ có MỘT hạn chốt cho cả chuyến, không chép mốc ấy vào từng đơn. Ba đơn đặt cách nhau
 * một tháng, nhưng khi mốc dịch thì cả ba mất quyền sửa cùng lúc - đó là thứ phải nhìn thấy tận
 * mắt, và chỉ nhìn thấy được khi thời điểm đặt của chúng khác nhau rõ rệt.
 *
 * ## Những gì seeder này cố ý KHÔNG làm
 *
 *   - Không tạo `schedule_audit_logs`. Nhật ký phải là thứ do chính tay người thử sinh ra, nếu
 *     không thì không phân biệt được dòng nào là bằng chứng thật.
 *   - Không đặt sẵn hạn chốt của Chuyến B vào quá khứ. Trạng thái đó nay phải đi qua giao diện
 *     (xem bước 13 trong bảng hướng dẫn), vì luật chặn mốc quá khứ chính là thứ cần thử.
 *   - Không gọi `ScheduleDeadlineService`. Mọi chuyến dựng thẳng bằng factory, nên khi mở màn nhật
 *     ký lần đầu nó phải trống trơn.
 *
 * Mốc thời gian tính lùi từ lúc chạy, nên seed lại trước mỗi buổi thử.
 *
 * Chạy riêng:  php artisan db:seed --class=BookingDeadlineScenarioSeeder
 *
 * Đọc kèm docs/nghiep-vu/16-sua-han-chot.md.
 */
class BookingDeadlineScenarioSeeder extends Seeder
{
    private const TOUR_SLUG = 'tour-thu-han-chot-danh-sach';

    /** Dấu để dọn sạch dữ liệu của lần seed trước mà không đụng dữ liệu khác. */
    private const TAG = '[han-chot]';

    private Tour $tour;
    private TourSchedule $chuyenA;
    private TourSchedule $chuyenB;
    private User $khach;
    private ?User $admin = null;

    /** @var array<string, Booking> */
    private array $don = [];

    public function run(): void
    {
        $this->admin = User::query()->where('role', 'admin')->orderBy('id')->first();

        if (!$this->admin) {
            $this->command?->warn(
                'Chưa có tài khoản admin nào. Chạy `php artisan db:seed` một lần trước đã, rồi seed lại lớp này.'
            );

            return;
        }

        $this->khach = $this->layTaiKhoanKhach();

        $this->donDepLanTruoc();
        $this->dungTour();
        $this->dungHaiChuyen();
        $this->dungBaDon();
        $this->dongBoSoCho();
        $this->inHuongDan();
    }

    /**
     * Cả ba đơn gắn vào một tài khoản khách, để đăng nhập một lần là xem được hết.
     *
     * Thực tế mỗi đơn một người, nhưng ở đây thứ cần so là quyền sửa theo thời điểm - không phải
     * quyền theo tài khoản. Dùng ba tài khoản chỉ tốn thêm ba lần đăng nhập.
     */
    private function layTaiKhoanKhach(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'customer@gmail.com'],
            [
                'name' => 'Customer User',
                'password' => bcrypt('customer123'),
                'role' => 'customer',
                'status' => 'active',
            ],
        );
    }

    /**
     * Xóa hẳn tour của lần seed trước.
     *
     * `withTrashed` + `forceDelete` là bắt buộc chứ không phải cho chắc: tour xóa mềm thì hàng cũ
     * vẫn giữ `slug`, và lần dựng sau vỡ vì trùng. Đơn phải đi trước vì khóa ngoại chặn xóa chuyến
     * khi còn đơn treo vào.
     *
     * Nhật ký chuyến của lần thử trước KHÔNG bị đụng tới, và cũng không đụng được: nó bất biến.
     * Xóa chuyến chỉ làm `tour_schedule_id` của nó thành rỗng, dòng nhật ký ở lại và màn hình gộp
     * hiện là "Chuyến đã xóa". Đó là đúng - bằng chứng không biến mất theo thứ nó nói về.
     */
    private function donDepLanTruoc(): void
    {
        $tourCu = Tour::withTrashed()->where('slug', self::TOUR_SLUG)->first();

        if ($tourCu) {
            Booking::query()->where('tour_id', $tourCu->id)->delete();
            Review::query()->where('tour_id', $tourCu->id)->delete();
            GroupBookingRequest::query()->where('tour_id', $tourCu->id)->delete();
            $tourCu->schedules()->delete();
            $tourCu->itineraries()->delete();
            $tourCu->forceDelete();
        }

        Booking::query()->where('note', 'like', self::TAG . '%')->delete();
    }

    private function dungTour(): void
    {
        $this->tour = Tour::factory()->create([
            'admin_id' => $this->admin->id,
            'cancellation_policy_id' => CancellationPolicy::dangApDung()?->id,
            'title' => 'Tour Thử Hạn Chốt Danh Sách 3N2Đ',
            'slug' => self::TOUR_SLUG,
            'description' => 'Tour dựng riêng để thử luồng dời hạn chốt danh sách khách. '
                . 'Không phải sản phẩm bán thật.',
            'adult_price' => 4000000,
            'child_price' => 2800000,
            'infant_price' => 0,
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'start_location' => 'Hà Nội',
            'end_location' => 'Hạ Long',
            'vehicle_info' => 'Xe 29 chỗ',
            'pickup_location' => 'Nhà hát Lớn Hà Nội',
            'type' => TourType::Shared->value,
            'is_featured' => false,
            'status' => 'active',
        ]);

        // Lùi ngày tạo về trước đơn cũ nhất, nếu không màn hình quản trị hiện ra một tour sinh sau
        // chính những đơn của nó.
        $this->tour->forceFill(['created_at' => now()->subDays(45)])->save();
    }

    /**
     * Hai chuyến, cùng một tour, cùng đang mở bán.
     *
     * Chuyến B tồn tại chỉ để làm đích cho luồng chuyển đơn. Nó phải khởi hành SAU chuyến A đủ xa:
     * hạn chốt mặc định của nó cũng phải còn ở tương lai, nếu không thì bước chuyển đơn hỏng vì một
     * lý do khác hẳn thứ đang thử.
     */
    private function dungHaiChuyen(): void
    {
        $this->chuyenA = $this->dungChuyen(now()->addDays(20)->setTime(6, 30));
        $this->chuyenB = $this->dungChuyen(now()->addDays(30)->setTime(6, 30));
    }

    private function dungChuyen(Carbon $batDau): TourSchedule
    {
        $chuyen = TourSchedule::factory()->create([
            'tour_id' => $this->tour->id,
            'start_date' => $batDau,
            'end_date' => $batDau->copy()->addDays(2),
            /*
             * Hỏi chính hệ thống mốc mặc định là bao nhiêu, không viết tay "trừ ba ngày".
             *
             * `booking.booking_deadline_days` sửa được, và một seeder tự nghĩ ra con số riêng sẽ
             * âm thầm lệch khỏi luật thật ngay lần đầu ai đó đổi cấu hình.
             */
            'booking_deadline' => TourSchedule::hanChotMacDinhTu($batDau),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 0,
            'status' => ScheduleStatus::Open->value,
            'is_private' => false,
        ]);

        $chuyen->forceFill(['created_at' => now()->subDays(45)])->save();

        return $chuyen->refresh();
    }

    /**
     * Ba đơn trên Chuyến A, khác nhau ở thời điểm đặt.
     *
     * Cả ba đều đặt TRƯỚC hạn chốt hiện tại của chuyến (khởi hành trừ 3 ngày, tức còn 17 ngày nữa).
     * Đó là điều kiện để bài thử có nghĩa: nếu có đơn nào đã nằm sau mốc thì lúc quyền sửa đóng
     * lại, không phân biệt được là do hạn chốt hay do đơn ấy vốn đã hết hạn.
     *
     * "Sát hạn" ở đây nghĩa là đặt gần hiện tại nhất, không phải sát mốc 17 ngày tới - không ai đặt
     * được một đơn ở tương lai. Nó thành đơn "sát hạn" thật sự ngay khi bạn rút hạn chốt về sát hôm
     * nay ở bước 7.
     */
    private function dungBaDon(): void
    {
        $this->don['som'] = $this->taoDon(
            ten: 'Khách Đặt Sớm - Test',
            dienThoai: '0900000001',
            datLuc: now()->subDays(30),
            nguoiLon: 2,
            daTra: true,
            ghiChu: 'đặt sớm nhất (30 ngày trước) — đơn dùng để chuyển sang Chuyến B',
        );

        $this->don['vua'] = $this->taoDon(
            ten: 'Khách Đặt Vừa - Test',
            dienThoai: '0900000002',
            datLuc: now()->subDays(7),
            nguoiLon: 1,
            treEm: 1,
            daTra: true,
            ghiChu: 'đặt giữa chừng (7 ngày trước) — đơn dùng để hủy sau hạn chốt',
        );

        $this->don['sat'] = $this->taoDon(
            ten: 'Khách Đặt Sát Hạn - Test',
            dienThoai: '0900000003',
            datLuc: now()->subHours(3),
            nguoiLon: 1,
            daTra: false,
            ghiChu: 'đặt gần nhất (3 giờ trước), còn giữ chỗ chưa trả tiền',
        );
    }

    private function taoDon(
        string $ten,
        string $dienThoai,
        Carbon $datLuc,
        int $nguoiLon,
        bool $daTra,
        string $ghiChu,
        int $treEm = 0,
    ): Booking {
        $factory = Booking::factory()
            ->choChuyen($this->chuyenA)
            ->cuaTaiKhoan($this->khach)
            ->soKhach($nguoiLon, $treEm)
            ->datLuc($datLuc);

        $don = ($daTra ? $factory->daThanhToan() : $factory->dangGiuCho())->create([
            'customer_name' => $ten,
            'customer_phone' => $dienThoai,
            'note' => self::TAG . ' ' . $ghiChu,
        ]);

        $this->khaiHanhKhach($don, $ten, $nguoiLon, $treEm);

        return $don;
    }

    /**
     * Khai đủ danh sách hành khách cho đơn.
     *
     * Khai đủ chứ không khai lấy lệ: `manifestWarnings()` cảnh báo khi số người khai ít hơn số
     * khách đã đặt, và chặn xuất danh sách đoàn. Đơn dựng sẵn mà đã dính cảnh báo thì mỗi bước thử
     * sau đó đều phải bỏ qua một dòng chữ đỏ không liên quan.
     */
    private function khaiHanhKhach(Booking $don, string $tenDon, int $nguoiLon, int $treEm): void
    {
        $goc = mb_strtoupper(str_replace(' - Test', '', $tenDon));

        for ($i = 0; $i < $nguoiLon; $i++) {
            $hanhKhach = BookingPassenger::factory()->nguoiLon();

            // Người đầu tiên của mỗi đơn là đầu mối liên lạc của đoàn.
            if ($i === 0) {
                $hanhKhach = $hanhKhach->nguoiLienHe();
            }

            $hanhKhach->create([
                'booking_id' => $don->id,
                'name' => $goc . ' ' . ($i + 1),
                'phone' => $don->customer_phone,
            ]);
        }

        for ($i = 0; $i < $treEm; $i++) {
            BookingPassenger::factory()->treEm()->create([
                'booking_id' => $don->id,
                'name' => $goc . ' (BE ' . ($i + 1) . ')',
            ]);
        }
    }

    /**
     * Số chỗ đã bán, tính theo đúng công thức lệnh đối chiếu C05 dùng.
     *
     * Gồm cả ghế chết - đơn đã hủy mà chỗ chưa trả về kho - vì đó cũng là chỗ không bán được nữa.
     * Ở lần seed đầu chưa có đơn hủy nào, nhưng công thức phải giống lệnh đối chiếu ngay từ đầu:
     * dữ liệu mẫu mà lệnh kiểm tra báo lệch thì không ai tin được kết quả của buổi thử.
     */
    private function dongBoSoCho(): void
    {
        foreach ([$this->chuyenA, $this->chuyenB] as $chuyen) {
            $soCho = (int) Booking::query()
                ->where('tour_schedule_id', $chuyen->id)
                ->where(function ($query) {
                    $query->where('status', '!=', 'cancelled')
                        ->orWhere(fn ($gheChet) => $gheChet
                            ->where('status', 'cancelled')
                            ->where('seats_released', false));
                })
                ->sum('guests');

            $chuyen->forceFill(['booked_people' => $soCho])->save();
        }
    }

    /**
     * In bảng hướng dẫn bằng đúng con số hiện trên màn hình.
     *
     * Người mở trang quản trị chỉ thấy "đơn BK-37" và "chuyến #12". Viết hướng dẫn bằng nhãn nội bộ
     * kiểu "đơn đặt sớm" là bắt họ tự dò một lần nữa, và đó là chỗ hướng dẫn thử tay hay đứt gãy
     * nhất.
     */
    private function inHuongDan(): void
    {
        $cmd = $this->command;

        if (!$cmd) {
            return;
        }

        $a = $this->chuyenA->refresh();
        $b = $this->chuyenB->refresh();

        $ma = fn (string $khoa): string => 'BK-' . $this->don[$khoa]->id;
        $ngay = fn (?Carbon $luc): string => $luc?->format('d/m/Y H:i') ?? '(không có)';

        $cmd->newLine();
        $cmd->info('=== DỮ LIỆU THỬ HẠN CHỐT DANH SÁCH ===');
        $cmd->newLine();

        $cmd->line(' Đăng nhập:  admin@gmail.com / admin123');
        $cmd->line('             customer@gmail.com / customer123');
        $cmd->newLine();

        $cmd->line(' Tour: ' . $this->tour->title . '  (/admin/tours, tìm "Thử Hạn Chốt")');
        $cmd->newLine();

        $cmd->comment(' HAI CHUYẾN — /admin/schedules, gõ số chuyến vào ô tìm kiếm');
        $cmd->line(sprintf(
            '   Chuyến A  #%-4d khởi hành %s   hạn chốt %s   %d/%d khách   %s',
            $a->id,
            $ngay($a->start_date),
            $ngay($a->booking_deadline),
            $a->booked_people,
            $a->max_people,
            $a->status->label(),
        ));
        $cmd->line(sprintf(
            '   Chuyến B  #%-4d khởi hành %s   hạn chốt %s   %d/%d khách   %s',
            $b->id,
            $ngay($b->start_date),
            $ngay($b->booking_deadline),
            $b->booked_people,
            $b->max_people,
            $b->status->label(),
        ));
        $cmd->newLine();

        $cmd->comment(' BA ĐƠN TRÊN CHUYẾN A — /admin/bookings, gõ mã đơn vào ô tìm kiếm');
        foreach (['som' => 'Đặt Sớm', 'vua' => 'Đặt Vừa', 'sat' => 'Sát Hạn'] as $khoa => $nhan) {
            $don = $this->don[$khoa];
            $cmd->line(sprintf(
                '   %-6s %-9s đặt lúc %s   %d khách   %s',
                $ma($khoa),
                $nhan,
                $ngay($don->created_at),
                $don->guests,
                $don->status,
            ));
        }
        $cmd->line('   Khai danh sách hành khách (không cần đăng nhập): /booking-success/<public_token>');
        foreach (['som', 'vua', 'sat'] as $khoa) {
            $cmd->line('     ' . $ma($khoa) . '  ' . $this->don[$khoa]->public_token);
        }
        $cmd->newLine();

        $cmd->line(sprintf(
            ' Nhật ký chuyến của hai chuyến này: %d dòng — phải là 0 trước khi bắt đầu.',
            ScheduleAuditLog::query()->whereIn('tour_schedule_id', [$a->id, $b->id])->count(),
        ));
        $cmd->newLine();

        $cmd->comment(' 14 BƯỚC — làm lần lượt từ trên xuống');
        foreach ($this->cacBuoc($a, $b, $ma) as $so => $viec) {
            $cmd->line(sprintf('  %2d. %s', $so + 1, $viec));
        }
        $cmd->newLine();

        $cmd->comment(' MẸO: thư gửi khách nằm ở storage/logs/laravel.log khi MAIL_MAILER=log.');
        $cmd->line('   Cả ba đơn dùng chung email ' . $this->khach->email . ', phân biệt bằng số đơn trong thân thư.');
        $cmd->newLine();
    }

    /**
     * @param  \Closure(string): string  $ma
     * @return array<int, string>
     */
    private function cacBuoc(TourSchedule $a, TourSchedule $b, \Closure $ma): array
    {
        return [
            "Mở /admin/schedules, xem Chuyến A #{$a->id}: đang mở bán, {$a->booked_people}/{$a->max_people} khách, hạn chốt "
                . $a->booking_deadline->format('d/m/Y H:i') . '. Đây là mốc gốc để so mọi bước sau.',

            'Mở trang tour phía khách, chọn Chuyến A: phải đặt được (còn trong hạn chốt, còn chỗ).',

            'Mở link khai hành khách của CẢ BA đơn ' . $ma('som') . ', ' . $ma('vua') . ', ' . $ma('sat')
                . ': cả ba đều sửa được tên, dù đặt cách nhau một tháng. Đây là mốc đối chứng của tiêu chí '
                . '"một hạn chốt cho mọi khách".',

            "Chuyến A -> \"Sửa hạn chốt\" -> chọn một ngày SAU ngày khởi hành -> phải bị chặn (hạn chốt phải trước ngày đi).",

            'Vẫn hộp thoại đó -> chọn ngày hôm qua -> phải bị chặn vì mốc nằm ở quá khứ. '
                . 'Đây là luật mới: khóa danh sách ngay thì đặt mốc = thời điểm hiện tại, không lùi về sau lưng.',

            'Chọn một mốc hợp lệ nhưng để TRỐNG ô lý do -> nút lưu không bấm được; gọi thẳng API cũng trả 422.',

            'Rút hạn chốt Chuyến A về "còn 2 giờ nữa", ghi lý do thật -> đọc bảng xem trước: phải liệt kê '
                . "{$a->booked_people} khách đang trong danh sách. Lưu, rồi kiểm hai thứ: (a) 3 thư trong laravel.log, "
                . '(b) đúng 1 dòng ở /admin/audit-logs với giá trị cũ, mới và lý do.',

            'Rút tiếp hạn chốt Chuyến A về thời điểm hiện tại -> mở lại link khai hành khách của cả ba đơn: '
                . 'CẢ BA cùng mất quyền sửa một lúc. Khách đặt sớm không được ưu ái, khách đặt muộn không bị phạt.',

            'Quay ra trang khách, thử đặt thêm một đơn vào Chuyến A -> bị từ chối "đã quá hạn đăng ký".',

            'Thử chuyển đơn ' . $ma('som') . " sang Chuyến B #{$b->id} -> bị chặn vì chuyến NGUỒN đã quá hạn chốt: "
                . 'suất ở chuyến A đã trả tiền cho nhà cung cấp, không rút lại được.',

            'Gia hạn Chuyến A về lại tương lai (ghi lý do) -> 3 thư nữa + dòng nhật ký thứ hai. '
                . 'Lưu ý chuyến KHÔNG tự mở bán lại: phải bấm "Mở bán" thì khách mới đặt tiếp được.',

            'Chuyển đơn ' . $ma('som') . " sang Chuyến B #{$b->id} -> lần này thành công. "
                . "Kiểm số chỗ hai đầu: Chuyến A giảm 2, Chuyến B tăng 2.",

            "Đưa Chuyến B vào thế quá hạn: sửa hạn chốt B thành thời điểm hiện tại, đợi một phút. "
                . 'Rồi thử chuyển đơn ' . $ma('vua') . ' sang B -> bị chặn vì chuyến ĐÍCH đã quá hạn chốt.',

            'Rút hạn chốt Chuyến A về hiện tại lần nữa, rồi hủy đơn ' . $ma('vua')
                . ' -> chỗ KHÔNG quay lại kho (ghế chết): booked_people của A không giảm. '
                . 'Cuối cùng mở /admin/audit-logs lọc theo Chuyến A: mọi lần dời mốc đều có dòng, không dòng nào sửa hay xóa được.',
        ];
    }
}
