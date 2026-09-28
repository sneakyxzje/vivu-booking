<?php

namespace Database\Seeders;

use App\Enums\GroupRequestStatus;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\CancellationPolicy;
use App\Models\GroupBookingRequest;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;


class DepositFlowSeeder extends Seeder
{
    private const TOUR_SLUG = 'tour-thu-nghiem-dat-coc';


    private const TAG = '[coc]';

    private Tour $tour;
    private User $khach;


    private array $bang = [];


    private array $chuyenTrong = [];

    public function run(): void
    {
        $admin = User::query()->where('role', 'admin')->first();

        if (!$admin) {
            $this->command?->warn('Chưa có tài khoản admin, bỏ qua DepositFlowSeeder.');

            return;
        }

        $this->khach = User::query()->firstOrCreate(
            ['email' => 'customer@gmail.com'],
            [
                'name' => 'Customer User',
                'password' => bcrypt('customer123'),
                'role' => 'customer',
                'status' => 'active',
            ],
        );

        $this->donDepLanTruoc();
        $this->dungTour($admin);
        $this->dungChuyenDeTuDat();
        $this->dungCacTinhHuong();
        $this->inHuongDan();
    }

    private function donDepLanTruoc(): void
    {
        $tourCu = Tour::withTrashed()->where('slug', self::TOUR_SLUG)->first();

        if ($tourCu) {
            // Xóa đơn trước: khóa ngoại của chúng chặn xóa chuyến, và khóa ngoại tour nay là
            // `restrict` nên tour cũng không xóa được khi còn đơn.
            Booking::query()->where('tour_id', $tourCu->id)->delete();
            GroupBookingRequest::query()->where('tour_id', $tourCu->id)->delete();
            $tourCu->schedules()->delete();
            $tourCu->itineraries()->delete();
            $tourCu->forceDelete();
        }

        Booking::query()->where('note', 'like', self::TAG . '%')->delete();
    }

    private function dungTour(User $admin): void
    {
        $this->tour = Tour::query()->create([
            'admin_id' => $admin->id,
            'title' => 'Tour Thử Nghiệm Đặt Cọc 3N2Đ',
            'slug' => self::TOUR_SLUG,
            'description' => 'Tour dựng riêng để thử luồng đặt cọc và thanh toán phần còn lại. '
                . 'Không phải sản phẩm bán thật.',
            'adult_price' => 5_000_000,
            'child_price' => 3_500_000,
            'infant_price' => 0,
            'thumbnail' => 'https://images.unsplash.com/photo-1528127269322-539801943592',
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'start_location' => 'Hà Nội',
            'end_location' => 'Hạ Long',
            'vehicle_info' => 'Xe 29 chỗ',
            'pickup_location' => 'Nhà hát Lớn Hà Nội',
            'status' => 'active',
        ]);
    }


    private function dungChuyenDeTuDat(): void
    {
        $hanTraNot = (int) config('booking.booking_deadline_days', 3);

        $xa = $this->taoChuyen($hanTraNot + 20);
        $satNgay = $this->taoChuyen(max(4, $hanTraNot - 3));

        $this->chuyenTrong = [
            [
                'ma' => 'Chuyến #' . $xa->id,
                'khoi_hanh' => $xa->start_date->format('d/m'),
                'ky_vong' => 'Đặt tour vào chuyến này: phải thấy "Đặt cọc 50%", còn lại trả sau.',
            ],
            [
                'ma' => 'Chuyến #' . $satNgay->id,
                'khoi_hanh' => $satNgay->start_date->format('d/m'),
                'ky_vong' => 'Đặt tour vào chuyến này: vẫn cọc 50%, trả nốt trước hạn chốt danh sách.',
            ],
        ];
    }


    private function dungCacTinhHuong(): void
    {
        $hanTraNot = (int) config('booking.booking_deadline_days', 3);
        $nhacLanDau = (int) config('booking.balance_reminder_days', 7);
        $canhBaoCuoi = (int) config('booking.balance_final_notice_days', 2);
        $tyLeCoc = (int) config('booking.deposit_percent', 50);

        // --- Ranh giới 1: cửa sổ nhắc -------------------------------------------------------

        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot + $nhacLanDau + 8,
            tyLeDaThu: $tyLeCoc,
            moTa: 'Vừa cọc xong, còn xa hạn',
            nhom: 'Nhắc trả nốt',
            kyVong: 'Lệnh nhắc: KHÔNG nhận thư nào — đối chứng.',
        );

        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot + $nhacLanDau - 1,
            tyLeDaThu: $tyLeCoc,
            moTa: 'Tới lượt nhắc lần đầu',
            nhom: 'Nhắc trả nốt',
            kyVong: 'Lệnh nhắc: thư nhắc nhẹ, nền xanh, có nút trả online.',
        );

        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot + $canhBaoCuoi - 1,
            tyLeDaThu: $tyLeCoc,
            moTa: 'Sát hạn, tới lượt cảnh báo cuối',
            nhom: 'Nhắc trả nốt',
            kyVong: 'Lệnh nhắc: thư nền ĐỎ, nói thẳng sẽ hủy đơn và mất cọc.',
        );

        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot + $nhacLanDau - 1,
            tyLeDaThu: $tyLeCoc,
            moTa: 'Tới lượt nhắc nhưng CHUYẾN ĐÃ HỦY',
            nhom: 'Nhắc trả nốt',
            kyVong: 'Lệnh nhắc: KHÔNG nhận thư — chuyến không chạy thì đòi tiền làm gì.',
            chuyenBiHuy: true,
        );

        // --- Ranh giới 2 và 3: hủy khi quá hạn ----------------------------------------------

        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot - 2,
            tyLeDaThu: $tyLeCoc,
            moTa: 'Đã cọc, đã nhận đủ 2 thư, đã quá hạn trả nốt',
            nhom: 'Hủy quá hạn',
            kyVong: 'Lệnh hủy: BỊ HỦY, hoàn 0 đồng (mất cọc), chỗ trả về kho.',
            daNhacCuoiTruoc: $canhBaoCuoi + 2,
        );


        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot - 2,
            tyLeDaThu: $tyLeCoc,
            moTa: 'Đã cọc, quá hạn, CHƯA nhận thư nào (vừa bị chuyển chuyến)',
            nhom: 'Hủy quá hạn',
            kyVong: 'Lệnh hủy: BỊ HỦY và mất cọc tại hạn chốt, không phụ thuộc lịch sử nhắc.',
        );

        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot - 2,
            tyLeDaThu: 100,
            moTa: 'Quá hạn nhưng đã trả đủ',
            nhom: 'Hủy quá hạn',
            kyVong: 'Lệnh hủy: KHÔNG bị đụng — đối chứng.',
        );

        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot - 2,
            tyLeDaThu: 0,
            moTa: 'Quá hạn, sổ ghi 0 đồng',
            nhom: 'Hủy quá hạn',
            kyVong: 'Lệnh hủy: KHÔNG bị hủy — có thể khách đã trả mà chưa ai ghi sổ. Điều hành xử lý tay.',
        );

        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot - 2,
            tyLeDaThu: $tyLeCoc,
            moTa: 'Đơn ĐOÀN quá hạn',
            nhom: 'Hủy quá hạn',
            kyVong: 'Lệnh hủy: KHÔNG bị hủy tự động — đoàn luôn có người theo.',
            laDoan: true,
        );

        // --- Ranh giới 4: thời điểm đặt ------------------------------------------------------

        $this->tinhHuong(
            ngayKhoiHanh: max(4, $hanTraNot - 3),
            tyLeDaThu: 100,
            moTa: 'Đặt sát ngày, đã trả đủ',
            nhom: 'Đặt sát ngày',
            kyVong: 'Không có khái niệm cọc. Lệnh nhắc và lệnh hủy đều bỏ qua.',
        );

        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot + $nhacLanDau + 8,
            tyLeDaThu: 0,
            moTa: 'Chờ thanh toán, chưa cọc',
            nhom: 'Đặt sát ngày',
            kyVong: 'Mở trang tra cứu: thấy nút "Đặt cọc 50%". Hết 10 phút thì tự hủy.',
            trangThai: 'pending',
        );
    }

    private function taoChuyen(int $ngayNua): TourSchedule
    {
        $start = Carbon::now()->addDays($ngayNua)->setTime(6, 0);

        return TourSchedule::query()->create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2)->setTime(18, 0),
            'booking_deadline' => $start->copy()->subDays((int) config('booking.booking_deadline_days', 3)),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 0,
        ]);
    }


    private function tinhHuong(
        int $ngayKhoiHanh,
        int $tyLeDaThu,
        string $moTa,
        string $nhom,
        string $kyVong,
        bool $laDoan = false,
        bool $chuyenBiHuy = false,
        string $trangThai = 'confirmed',
        ?int $daNhacCuoiTruoc = null,
    ): void {
        $chuyen = $this->taoChuyen($ngayKhoiHanh);
        $tongTien = 2 * (float) $this->tour->adult_price;
        $daThu = round($tongTien * $tyLeDaThu / 100);

        $don = Booking::query()->create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_id' => $this->khach->id,
            'customer_name' => $this->khach->name,
            'customer_email' => $this->khach->email,
            'customer_phone' => '0901234567',
            'departure_date' => $chuyen->start_date,
            'guests' => 2,
            'seats' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'adult_price' => $this->tour->adult_price,
            'child_price' => $this->tour->child_price,
            'infant_price' => $this->tour->infant_price,
            'total_amount' => $tongTien,
            'status' => $trangThai,
            'confirmed_at' => $trangThai === 'confirmed' ? now()->subDays(3) : null,
            'expires_at' => $trangThai === 'pending'
                ? now()->addMinutes((int) config('booking.payment_ttl_minutes', 10))
                : null,
            'cancellation_policy_id' => CancellationPolicy::dangApDung()?->id,
            'note' => self::TAG . ' ' . $moTa,
        ]);


        if ($daThu > 0) {
            BookingPayment::query()->create([
                'booking_id' => $don->id,
                'kind' => $tyLeDaThu >= 100 ? 'balance' : 'deposit',
                'amount' => $daThu,
                'method' => 'gateway',
                'reference' => 'SEED-' . Str::upper(Str::random(8)),
                'note' => $tyLeDaThu >= 100 ? 'Thanh toán đủ' : 'Đặt cọc giữ chỗ',
                'paid_at' => now()->subDays(3),
            ]);
        }

        if ($tyLeDaThu >= 100) {
            $don->forceFill(['paid_at' => now()->subDays(3)])->save();
        }

        if ($laDoan) {
            $yeuCau = GroupBookingRequest::query()->create([
                'public_token' => (string) Str::uuid(),
                'tour_id' => $this->tour->id,
                'tour_schedule_id' => $chuyen->id,
                'customer_id' => $this->khach->id,
                'contact_name' => 'Công ty ABC',
                'contact_email' => $this->khach->email,
                'contact_phone' => '0901234567',
                'estimated_guests' => 2,
                'quoted_price_per_person' => $this->tour->adult_price,
                'quoted_free_slots' => 0,
                'status' => GroupRequestStatus::Confirmed,
                'decided_at' => now()->subDays(3),
            ]);

            $don->forceFill(['group_booking_request_id' => $yeuCau->id])->save();
        }


        if ($daNhacCuoiTruoc !== null) {
            $don->forceFill([
                'balance_reminder_sent_at' => now()->subDays($daNhacCuoiTruoc + 5),
                'balance_final_notice_at' => now()->subDays($daNhacCuoiTruoc),
            ])->save();
        }

        $chuyen->forceFill(['booked_people' => 2])->save();


        if ($chuyenBiHuy) {
            $chuyen->forceFill([
                'status' => ScheduleStatus::Cancelled->value,
                'cancelled_at' => now()->subDay(),
                'cancelled_reason' => 'Dựng sẵn để thử: chuyến đã hủy thì không đòi tiền khách nữa.',
            ])->save();
        }

        $this->bang[] = [
            'nhom' => $nhom,
            'don' => 'BK-' . $don->id,
            'khoi_hanh' => $chuyen->start_date->format('d/m'),
            'han_tra_not' => $don->balanceDueAt()?->format('d/m') ?? '—',
            'da_thu' => number_format($daThu, 0, ',', '.'),
            'con_thieu' => number_format($tongTien - $daThu, 0, ',', '.'),
            'tinh_huong' => $moTa,
            'ky_vong' => $kyVong,
        ];
    }


    private function inHuongDan(): void
    {
        $cmd = $this->command;

        if (!$cmd) {
            return;
        }

        $cmd->newLine();
        $cmd->info('Đã dựng ' . count($this->bang) . ' đơn và 2 chuyến trống cho luồng đặt cọc.');
        $cmd->line('Tour: ' . $this->tour->title . '  ·  Đăng nhập khách: customer@gmail.com / customer123');
        $cmd->newLine();

        $cmd->table(
            ['Nhóm', 'Đơn', 'Khởi hành', 'Hạn trả nốt', 'Đã thu', 'Còn thiếu', 'Tình huống'],
            array_map(fn (array $d) => [
                $d['nhom'], $d['don'], $d['khoi_hanh'], $d['han_tra_not'],
                $d['da_thu'], $d['con_thieu'], $d['tinh_huong'],
            ], $this->bang),
        );

        $cmd->newLine();
        $cmd->line('<comment>Kỳ vọng của từng đơn:</comment>');

        foreach ($this->bang as $d) {
            $cmd->line("  <info>{$d['don']}</info>  {$d['ky_vong']}");
        }

        $cmd->newLine();
        $cmd->line('<comment>Hai chuyến để trống, dùng để TỰ ĐẶT TOUR trên giao diện:</comment>');

        foreach ($this->chuyenTrong as $c) {
            $cmd->line("  <info>{$c['ma']}</info> khởi hành {$c['khoi_hanh']}  ·  {$c['ky_vong']}");
        }

        $cmd->newLine();
        $cmd->line('<comment>Ba lệnh để chạy thử, theo đúng thứ tự này:</comment>');
        $cmd->line('  1. php artisan bookings:send-balance-reminders');
        $cmd->line('     → chỉ 2 đơn nhận thư: một nhắc nhẹ, một cảnh báo cuối');
        $cmd->line('  2. php artisan bookings:cancel-unpaid-balances --dry-run');
        $cmd->line('     → chỉ liệt kê, phải thấy ĐÚNG 1 đơn');
        $cmd->line('  3. php artisan bookings:cancel-unpaid-balances');
        $cmd->line('     → hủy thật, rồi mở Thông báo ở trang quản trị');
        $cmd->newLine();
        $cmd->line('<comment>Xem trên giao diện:</comment>');
        $cmd->line('  · Đơn hàng → Sổ giao dịch → tab "Phải thu": các đơn còn nợ');
        $cmd->line('  · Đơn hàng → Hoá đơn: mỗi dòng hiện "Thiếu ... đ"');
        $cmd->line('  · Mở một đơn → nút "Xác nhận đã trả nốt" để thu offline');
        $cmd->line('  · Trang khách → đặt tour vào hai chuyến trống ở trên để so cọc với trả đủ');
        $cmd->newLine();
    }
}
