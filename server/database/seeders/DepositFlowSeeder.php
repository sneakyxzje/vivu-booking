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

/**
 * Dựng sẵn sáu đơn ở sáu chặng của luồng "cọc trước, trả nốt sau", để thử tay ngay.
 *
 * Chạy riêng:  php artisan db:seed --class=DepositFlowSeeder
 *
 * Mốc thời gian tính lùi từ lúc chạy seeder theo đúng ba con số trong `config/booking.php`, nên
 * chạy lại lúc nào cũng ra đúng tình huống ấy. Đổi lại, dữ liệu cũ trôi khỏi mốc sau vài ngày:
 * seed lại trước mỗi buổi thử.
 *
 * Sáu đơn cố ý phủ cả hai phía của mỗi ranh giới — một đơn chưa tới lượt và một đơn vừa qua lượt —
 * vì một lệnh nền chỉ chứng minh được là nó đúng khi nó vừa làm gì đó với đơn này và vừa BỎ QUA
 * đơn kia. Chỉ dựng toàn đơn sẽ bị xử lý thì không phân biệt được "lệnh chạy đúng" với "lệnh quét
 * sạch mọi thứ".
 */
class DepositFlowSeeder extends Seeder
{
    private const TOUR_SLUG = 'tour-thu-nghiem-dat-coc';

    /** Dấu để lần seed sau dọn sạch lần trước, không đụng dữ liệu khác. */
    private const TAG = '[coc]';

    private Tour $tour;
    private User $khach;

    /** @var array<int, array<string, mixed>> */
    private array $bang = [];

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

    /**
     * Sáu tình huống, xếp theo trục thời gian của một đơn.
     *
     * Các mốc suy ra từ cấu hình chứ không viết cứng: đổi `balance_due_days` mà seeder vẫn dựng
     * theo số cũ thì bảng hướng dẫn in ra sẽ nói sai về chính dữ liệu nó vừa tạo.
     */
    private function dungCacTinhHuong(): void
    {
        $hanTraNot = (int) config('booking.balance_due_days', 10);
        $nhacLanDau = (int) config('booking.balance_reminder_days', 7);
        $canhBaoCuoi = (int) config('booking.balance_final_notice_days', 2);
        $tyLeCoc = (int) config('booking.deposit_percent', 50);

        // Còn xa: hạn trả nốt vẫn cách cửa sổ nhắc vài ngày.
        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot + $nhacLanDau + 8,
            tyLeDaThu: $tyLeCoc,
            moTa: 'Vừa cọc xong, còn xa hạn',
            kyVong: 'Chạy lệnh nhắc: KHÔNG nhận thư nào. Đây là đơn đối chứng.',
        );

        // Vừa vào cửa sổ nhắc lần đầu.
        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot + $nhacLanDau - 1,
            tyLeDaThu: $tyLeCoc,
            moTa: 'Tới lượt nhắc lần đầu',
            kyVong: 'Chạy lệnh nhắc: nhận thư nhắc nhẹ, nền xanh, có nút trả online.',
        );

        // Sát hạn: rơi vào ngưỡng cảnh báo cuối.
        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot + $canhBaoCuoi - 1,
            tyLeDaThu: $tyLeCoc,
            moTa: 'Sát hạn, tới lượt cảnh báo cuối',
            kyVong: 'Chạy lệnh nhắc: nhận thư nền ĐỎ, nói thẳng sẽ hủy đơn và mất cọc.',
        );

        // Đã quá hạn hai ngày.
        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot - 2,
            tyLeDaThu: $tyLeCoc,
            moTa: 'Đã quá hạn trả nốt',
            kyVong: 'Chạy lệnh hủy: đơn bị hủy, hoàn 0 đồng (mất cọc), chỗ trả về kho.',
        );

        // Quá hạn nhưng đã trả đủ — đối chứng cho lệnh hủy.
        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot - 2,
            tyLeDaThu: 100,
            moTa: 'Quá hạn nhưng đã trả đủ',
            kyVong: 'Chạy lệnh hủy: KHÔNG bị đụng tới. Đây là đơn đối chứng.',
        );

        // Đơn đoàn quá hạn — đối chứng cho luật loại trừ đoàn.
        $this->tinhHuong(
            ngayKhoiHanh: $hanTraNot - 2,
            tyLeDaThu: $tyLeCoc,
            moTa: 'Đơn ĐOÀN quá hạn',
            kyVong: 'Chạy lệnh hủy: KHÔNG bị hủy tự động, vì đoàn luôn có người theo.',
            laDoan: true,
        );
    }

    /**
     * Dựng một chuyến và một đơn ở đúng mốc mong muốn.
     *
     * @param  int  $ngayKhoiHanh  số ngày nữa thì chuyến khởi hành
     * @param  int  $tyLeDaThu  phần trăm giá đơn đã nằm trong sổ
     */
    private function tinhHuong(
        int $ngayKhoiHanh,
        int $tyLeDaThu,
        string $moTa,
        string $kyVong,
        bool $laDoan = false,
    ): void {
        $start = Carbon::now()->addDays($ngayKhoiHanh)->setTime(6, 0);

        $chuyen = TourSchedule::query()->create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2)->setTime(18, 0),
            'booking_deadline' => $start->copy()->subDays((int) config('booking.booking_deadline_days', 3)),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 0,
        ]);

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
            'departure_date' => $start,
            'guests' => 2,
            'seats' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'adult_price' => $this->tour->adult_price,
            'child_price' => $this->tour->child_price,
            'infant_price' => $this->tour->infant_price,
            'total_amount' => $tongTien,
            'status' => 'confirmed',
            'confirmed_at' => now()->subDays(3),
            'cancellation_policy_id' => CancellationPolicy::dangApDung()?->id,
            'note' => self::TAG . ' ' . $moTa,
        ]);

        /*
         * Ghi tiền vào SỔ, và chỉ đóng `paid_at` khi thu đủ.
         *
         * Đúng cách luồng thật làm. Đóng mốc ấy cho đơn mới cọc là dựng sẵn một dữ liệu nói dối:
         * mọi phép tính tiền sẽ tưởng đơn đã trả xong.
         */
        BookingPayment::query()->create([
            'booking_id' => $don->id,
            'kind' => $tyLeDaThu >= 100 ? 'balance' : 'deposit',
            'amount' => $daThu,
            'method' => 'gateway',
            'reference' => 'SEED-' . Str::upper(Str::random(8)),
            'note' => $tyLeDaThu >= 100 ? 'Thanh toán đủ' : 'Đặt cọc giữ chỗ',
            'paid_at' => now()->subDays(3),
        ]);

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

        $chuyen->forceFill(['booked_people' => 2])->save();

        $this->bang[] = [
            'don' => 'BK-' . $don->id,
            'khoi_hanh' => $start->format('d/m'),
            'han_tra_not' => $don->balanceDueAt()?->format('d/m') ?? '—',
            'da_thu' => number_format($daThu, 0, ',', '.'),
            'con_thieu' => number_format($tongTien - $daThu, 0, ',', '.'),
            'tinh_huong' => $moTa,
            'ky_vong' => $kyVong,
        ];
    }

    /**
     * In bảng hướng dẫn bằng đúng mã đơn người thử sẽ nhìn thấy trên màn hình.
     *
     * Nhãn kịch bản kiểu "đơn số 3" không có ích: người mở trang quản trị chỉ thấy BK-37, và bắt họ
     * tự dò xem cái nào ứng với nhãn nào là chỗ hướng dẫn thử tay hay đứt gãy nhất.
     */
    private function inHuongDan(): void
    {
        $cmd = $this->command;

        if (!$cmd) {
            return;
        }

        $cmd->newLine();
        $cmd->info('Đã dựng ' . count($this->bang) . ' đơn cho luồng đặt cọc.');
        $cmd->line('Tour: ' . $this->tour->title . '  ·  Đăng nhập khách: customer@gmail.com / customer123');
        $cmd->newLine();

        $cmd->table(
            ['Đơn', 'Khởi hành', 'Hạn trả nốt', 'Đã thu', 'Còn thiếu', 'Tình huống'],
            array_map(fn (array $d) => [
                $d['don'], $d['khoi_hanh'], $d['han_tra_not'], $d['da_thu'], $d['con_thieu'], $d['tinh_huong'],
            ], $this->bang),
        );

        $cmd->newLine();
        $cmd->line('<comment>Kỳ vọng của từng đơn:</comment>');

        foreach ($this->bang as $d) {
            $cmd->line("  <info>{$d['don']}</info>  {$d['ky_vong']}");
        }

        $cmd->newLine();
        $cmd->line('<comment>Ba lệnh để chạy thử, theo đúng thứ tự này:</comment>');
        $cmd->line('  1. php artisan bookings:send-balance-reminders');
        $cmd->line('     → xem thư ở storage/logs hoặc Mailtrap tùy MAIL_MAILER');
        $cmd->line('  2. php artisan bookings:cancel-unpaid-balances --dry-run');
        $cmd->line('     → chỉ liệt kê đơn sẽ bị hủy, không đụng gì');
        $cmd->line('  3. php artisan bookings:cancel-unpaid-balances');
        $cmd->line('     → hủy thật, rồi mở Thông báo ở trang quản trị để thấy báo chuyến trống chỗ');
        $cmd->newLine();
        $cmd->line('<comment>Xem trên giao diện:</comment>');
        $cmd->line('  · Quản trị → Đơn hàng → Sổ giao dịch → tab "Phải thu": thấy các đơn còn nợ');
        $cmd->line('  · Quản trị → Đơn hàng → Hoá đơn: mỗi dòng hiện "Thiếu ... đ"');
        $cmd->line('  · Mở một đơn → nút "Xác nhận đã trả nốt" để thu offline');
        $cmd->newLine();
    }
}
