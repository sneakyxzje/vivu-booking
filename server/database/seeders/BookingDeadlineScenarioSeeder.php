<?php

namespace Database\Seeders;

use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Models\Booking;
use App\Models\BookingPassenger;
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
            // Bốn mốc như các seeder khác, để bảng giờ hai chiều trên trang chi tiết có đủ số.
            'end_date' => $batDau->copy()->addDays(2)->setTime(18, 0),
            'arrival_at' => $batDau->copy()->addHours(6),
            'return_departure_at' => $batDau->copy()->addDays(2)->setTime(13, 0),
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
        $cmd->line('   Link khai hành khách (không cần đăng nhập), dán thẳng vào trình duyệt:');
        foreach (['som', 'vua', 'sat'] as $khoa) {
            $cmd->line(sprintf(
                '     %-6s /bookings/%s/passengers',
                $ma($khoa),
                $this->don[$khoa]->public_token,
            ));
        }
        $cmd->newLine();

        $cmd->line(sprintf(
            ' Nhật ký chuyến của hai chuyến này: %d dòng — phải là 0 trước khi bắt đầu.',
            ScheduleAuditLog::query()->whereIn('tour_schedule_id', [$a->id, $b->id])->count(),
        ));
        $cmd->newLine();

        $cmd->comment(' CÁCH ĐỌC 14 BƯỚC DƯỚI ĐÂY');
        $cmd->line('   "bây giờ"  = lúc BẠN đang bấm, không phải lúc chạy seeder.');
        $cmd->line('   Mốc trong ngoặc là ví dụ tính lúc seed (' . now()->format('d/m/Y H:i') . '),');
        $cmd->line('   chạy sau vài tiếng thì con số lệch đi, nhưng cách làm thì không đổi.');
        $cmd->line('   Thư gửi khách nằm ở storage/logs/laravel.log khi MAIL_MAILER=log; cả ba đơn');
        $cmd->line('   dùng chung email ' . $this->khach->email . ', phân biệt bằng số đơn trong thân thư.');

        foreach ($this->cacBuoc($a, $b, $ma) as $i => $buoc) {
            $cmd->newLine();
            $cmd->comment(sprintf('  BƯỚC %d — %s', $i + 1, $buoc['ten']));

            foreach ($buoc['lam'] as $j => $dong) {
                $cmd->line('     ' . ($j === 0 ? 'Làm:  ' : '       ') . $dong);
            }

            foreach ($buoc['dung'] as $j => $dong) {
                $cmd->line('     ' . ($j === 0 ? 'Đúng: ' : '       ') . $dong);
            }

            foreach ($buoc['vi'] ?? [] as $j => $dong) {
                $cmd->line('     ' . ($j === 0 ? 'Vì:   ' : '       ') . $dong);
            }
        }

        $cmd->newLine();
    }

    /**
     * Mười bốn bước, mỗi bước nói đúng ba điều: làm gì, thấy gì là đúng, và vì sao.
     *
     * Mọi mốc thời gian đều viết ra thành một giá trị cụ thể. Câu kiểu "rút về còn 2 giờ nữa" đọc
     * xong vẫn phải đoán là 2 giờ tính từ đâu, mà đoán sai một mốc thì cả bước sau đó ra kết quả
     * khác hẳn và người thử tưởng hệ thống hỏng.
     *
     * @param  \Closure(string): string  $ma
     * @return array<int, array{ten: string, lam: array<int, string>, dung: array<int, string>, vi?: array<int, string>}>
     */
    private function cacBuoc(TourSchedule $a, TourSchedule $b, \Closure $ma): array
    {
        $sua = fn (TourSchedule $c): string => sprintf(
            '/admin/schedules -> tìm chuyến #%d -> nút "Sửa hạn chốt"',
            $c->id,
        );

        $gio = fn (Carbon $luc): string => $luc->format('d/m/Y H:i');

        $mocGoc = $gio($a->booking_deadline);
        $sauKhoiHanh = $gio($a->start_date->copy()->addDay());
        $homQua = $gio(now()->subDay()->setTime(8, 0));
        $haiTiengNua = $gio(now()->addHours(2));
        $phutNay = $gio(now());
        $giaHanToi = $gio($a->start_date->copy()->subDays(2));
        $rutHaiNgay = $gio($a->booking_deadline->copy()->subDays(2));

        return [
            [
                'ten' => 'Nhìn mốc gốc, chưa đụng vào gì',
                'lam' => [
                    '/admin/schedules -> tìm chuyến #' . $a->id,
                    'Rồi mở trang tour phía khách, chọn chuyến khởi hành ' . $gio($a->start_date),
                ],
                'dung' => [
                    sprintf('Chuyến #%d: Đang mở bán, %d/%d khách, hạn chốt %s',
                        $a->id, $a->booked_people, $a->max_people, $mocGoc),
                    'Trang khách: chuyến này đặt được bình thường.',
                ],
            ],
            [
                'ten' => 'Ba đơn đều sửa được tên — đây là mốc đối chứng',
                'lam' => [
                    'Mở link khai hành khách của cả ba đơn: ' . $ma('som') . ', ' . $ma('vua') . ', ' . $ma('sat'),
                    '(link in ở phần trên, dạng /bookings/<public_token>/passengers)',
                ],
                'dung' => ['Cả ba đơn đều sửa được tên hành khách.'],
                'vi' => [
                    'Ba đơn này đặt cách nhau một tháng. Bước 7 sẽ cho thấy chúng mất quyền sửa',
                    'cùng một lúc — hệ thống không xét ai đặt trước ai đặt sau.',
                ],
            ],
            [
                'ten' => 'Hạn chốt sau ngày khởi hành -> bị chặn',
                'lam' => [
                    $sua($a),
                    'Chọn hạn chốt = ' . $sauKhoiHanh . '  (sau ngày đi)',
                ],
                'dung' => ['Máy chủ từ chối: "Hạn chốt phải trước ngày khởi hành ..."'],
                'vi' => ['Sau ngày đi thì mốc này không còn nghĩa gì: khách đặt được tới lúc xe lăn bánh.'],
            ],
            [
                'ten' => 'Hạn chốt đã trôi qua -> bị chặn',
                'lam' => [
                    $sua($a),
                    'Chọn hạn chốt = ' . $homQua . '  (hôm qua)',
                ],
                'dung' => ['Máy chủ từ chối: "Hạn chốt mới (...) nằm ở quá khứ ..."'],
                'vi' => [
                    'Luật KHÔNG phải "lùi nhiều thì cấm, lùi ít thì cho". Chỉ có một câu hỏi:',
                    'mốc mới nằm TRƯỚC hay SAU thời điểm bạn bấm lưu.',
                    '   ' . $mocGoc . ' -> ' . $rutHaiNgay . '   ĐƯỢC (vẫn ở tương lai, dù đã rút 2 ngày)',
                    '   ' . $mocGoc . ' -> ' . $haiTiengNua . '   ĐƯỢC (còn 2 tiếng nữa mới tới)',
                    '   ' . $mocGoc . ' -> ' . $homQua . '   KHÔNG (đã trôi qua rồi)',
                    'Đặt mốc vào hôm qua là tuyên bố một điều chưa từng đúng: hôm qua chuyến vẫn',
                    'bán chỗ, vẫn cho sửa tên. Muốn khóa NGAY thì dùng bước 7.',
                ],
            ],
            [
                'ten' => 'Bỏ trống ô lý do -> không lưu được',
                'lam' => [
                    $sua($a),
                    'Chọn một mốc hợp lệ (ví dụ ' . $rutHaiNgay . ') nhưng để trống ô "Lý do dời hạn"',
                ],
                'dung' => [
                    'Nút "Đồng ý, lưu hạn chốt mới" mờ đi, không bấm được.',
                    'Gọi thẳng API không kèm reason cũng trả 422.',
                ],
            ],
            [
                'ten' => 'Rút hạn chốt xuống còn 2 tiếng nữa (lần dời thật đầu tiên)',
                'lam' => [
                    $sua($a),
                    'Chọn hạn chốt = HÔM NAY, giờ hiện tại cộng 2 tiếng  (lúc seed là ' . $haiTiengNua . ')',
                    'Lý do: gõ một câu thật, ví dụ "Nha xe chot ghe som hon mot ngay"',
                    'Đọc bảng xem trước rồi mới bấm lưu.',
                ],
                'dung' => [
                    sprintf('Bảng xem trước nói: %d đơn / %d khách đang trong danh sách đoàn.',
                        count($this->don), $a->booked_people),
                    'Sau khi lưu: 3 thư mới trong storage/logs/laravel.log.',
                    '/admin/audit-logs có ĐÚNG 1 dòng, ghi mốc cũ ' . $mocGoc . ' -> mốc mới, kèm lý do.',
                ],
                'vi' => ['"2 tiếng nữa" là tính từ BÂY GIỜ, không phải từ hạn chốt cũ.'],
            ],
            [
                'ten' => 'Khóa danh sách ngay: đặt hạn chốt = phút hiện tại',
                'lam' => [
                    $sua($a),
                    'Chọn hạn chốt = đúng ngày giờ hiện tại  (lúc seed là ' . $phutNay . ')',
                    'Ghi lý do, lưu. Đợi sang phút kế tiếp.',
                    'Rồi mở lại link khai hành khách của cả ba đơn ở bước 2.',
                ],
                'dung' => [
                    'CẢ BA đơn cùng mất quyền sửa tên, không đơn nào được ưu ái.',
                    'Khách thấy: "Đã qua hạn chốt danh sách... liên hệ bộ phận điều hành".',
                ],
                'vi' => [
                    'Đây là cách khóa danh sách ngay hôm nay, thay cho việc đặt mốc vào quá khứ.',
                    'Điều hành vẫn sửa được danh sách; chỉ khách là không.',
                ],
            ],
            [
                'ten' => 'Chuyến A không nhận đặt mới nữa',
                'lam' => ['Ra trang tour phía khách, thử đặt một đơn mới vào chuyến ' . $gio($a->start_date)],
                'dung' => ['Chuyến hiện "Đã quá hạn đăng ký", không chọn để đặt được.'],
            ],
            [
                'ten' => 'Không chuyển đơn ra khỏi chuyến đã quá hạn',
                'lam' => [
                    '/admin/bookings -> tìm ' . $ma('som') . ' -> mở chi tiết -> "Chuyển chuyến"',
                    'Trong hộp thoại, mục "1. Trao đổi với khách": bấm "Ghi nhận cuộc liên hệ",',
                    'chọn kết quả "Khách đồng ý", lưu. Đây là căn cứ bắt buộc, thiếu nó thì máy chủ',
                    'từ chối trước cả khi xét tới hạn chốt.',
                    'Rồi chọn chuyến đích #' . $b->id . ' và bấm chuyển.',
                ],
                'dung' => [
                    'Bị chặn: "Chuyến hiện tại đã qua hạn chốt danh sách ngày ..."',
                    'Trong danh sách chuyến đích, MỌI chuyến đều báo không chuyển được — lỗi nằm ở',
                    'chuyến nguồn chứ không ở chuyến nào cụ thể.',
                ],
                'vi' => ['Suất ở chuyến A đã trả tiền cho nhà cung cấp rồi, không rút lại được.'],
            ],
            [
                'ten' => 'Chạy lệnh nền để chuyến tự đóng bán',
                'lam' => ['Ở terminal: php artisan schedules:close-expired'],
                'dung' => ['Chuyến #' . $a->id . ' chuyển từ "Đang mở bán" sang "Đã đóng bán".'],
                'vi' => [
                    'Ngoài đời lệnh này chạy theo lịch. Không gọi tay thì chuyến vẫn "mở bán" dù đã',
                    'quá hạn — và bước 11 sẽ không thấy được cái bẫy của nó.',
                ],
            ],
            [
                'ten' => 'Gia hạn: chuyến KHÔNG tự mở bán lại',
                'lam' => [
                    $sua($a),
                    'Chọn hạn chốt = ' . $giaHanToi . '  (kéo về tương lai)',
                    'Ghi lý do, lưu.',
                ],
                'dung' => [
                    'Thêm 3 thư nữa trong laravel.log, và dòng nhật ký thứ hai ở /admin/audit-logs.',
                    'Chuyến VẪN "Đã đóng bán" -> phải bấm "Mở bán" thì khách mới đặt lại được.',
                ],
                'vi' => ['Mở bán lại là quyết định của người, không phải hệ quả tự động của việc dời mốc.'],
            ],
            [
                'ten' => 'Chuyển đơn sang chuyến B — lần này được',
                'lam' => [
                    '/admin/bookings -> ' . $ma('som') . ' -> "Chuyển chuyến" -> chọn chuyến #' . $b->id,
                    'Căn cứ ghi ở bước 9 vẫn dùng lại được, vì lần đó chuyển không thành.',
                ],
                'dung' => [
                    'Chuyển thành công.',
                    sprintf('Số chỗ: chuyến #%d còn %d khách, chuyến #%d có 2 khách.',
                        $a->id, $a->booked_people - 2, $b->id),
                ],
            ],
            [
                'ten' => 'Chuyến ĐÍCH quá hạn cũng không nhận khách',
                'lam' => [
                    $sua($b) . '  (chuyến B, không phải A)',
                    'Chọn hạn chốt = đúng ngày giờ hiện tại, ghi lý do, lưu. Đợi sang phút kế tiếp.',
                    'Rồi thử chuyển đơn ' . $ma('vua') . ' sang chuyến #' . $b->id,
                    '(đơn này chưa có căn cứ nào, nên phải ghi một cuộc liên hệ riêng cho nó)',
                ],
                'dung' => ['Bị chặn: "Chuyến đích đã qua hạn chốt danh sách ngày ..."'],
                'vi' => [
                    'Nhận thêm khách vào chuyến đã chốt danh sách là vượt số suất đã đặt với nhà',
                    'cung cấp — dù khách ấy đến từ đâu.',
                ],
            ],
            [
                'ten' => 'Hủy sau hạn chốt sinh ghế chết, và đọc lại nhật ký',
                'lam' => [
                    $sua($a) . ' -> đặt hạn chốt = ngày giờ hiện tại, ghi lý do, lưu.',
                    'Ghi lại số khách của chuyến #' . $a->id . ' đang hiện trên màn hình.',
                    '/admin/bookings -> ' . $ma('vua') . ' -> "Hủy đơn" -> đọc bảng dự báo -> xác nhận.',
                    'Cuối cùng mở /admin/audit-logs, lọc theo chuyến #' . $a->id . '.',
                ],
                'dung' => [
                    'Bảng dự báo cảnh báo chỗ sẽ KHÔNG quay lại kho.',
                    'Sau khi hủy: số khách của chuyến không giảm (đó là ghế chết).',
                    'Nhật ký có đủ mọi lần dời mốc, mỗi dòng đủ cũ/mới/ai/lý do, không sửa xóa được.',
                ],
            ],
        ];
    }
}
