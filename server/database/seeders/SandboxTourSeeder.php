<?php

namespace Database\Seeders;

use App\Enums\GroupRequestStatus;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\CancellationPolicy;
use App\Models\CustomerContactLog;
use App\Models\GroupBookingRequest;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Ba tour SÂN THỬ NGHIỆM: nơi mọi tình huống nghiệp vụ được dựng sẵn để bấm nút chứng minh.
 *
 * Chạy riêng:  php artisan db:seed --class=SandboxTourSeeder
 *
 * ## Vì sao tách hẳn khỏi catalogue
 *
 * Tour bán thật và tour để chứng minh nghiệp vụ cần hai thứ ngược nhau. Tour thật phải trông như
 * hàng thật: ảnh đẹp, lịch trình gọn, vài chuyến sắp tới. Tour thử phải có **đủ mọi ngã rẽ cùng
 * lúc** — đơn mới cọc, đơn quá hạn, đơn đã hủy còn nợ tiền hoàn, đơn đoàn, đơn sổ ghi 0 đồng — và
 * một bộ dữ liệu như thế trên trang khách thì trông như một hệ thống đang hỏng.
 *
 * Ba tour dưới đây mang cờ `is_sandbox`, nên chúng mở được quyền **tua ngày khởi hành** mà toàn hệ
 * thống còn lại cấm (xem `SandboxDemoService`). Không có quyền ấy thì muốn xem lệnh hủy chạy phải
 * chờ mười ngày.
 *
 * ## Chia theo ba chủ đề, không trộn
 *
 *   1. **Tiền vào tiền ra** — cọc, trả nốt, nhắc, hủy quá hạn, hoàn tiền.
 *   2. **Ghép và chuyển chuyến** — hai chuyến cùng tour để ghép, và nhật ký liên hệ dựng sẵn để
 *      chuyển chuyến bấm được ngay (không có nhật ký thì hệ thống chặn, đúng luật).
 *   3. **Vòng đời chuyến** — hạn chốt, chốt chạy, thiếu khách, hủy cả chuyến.
 *
 * Trộn cả ba vào một tour thì mỗi lần tua ngày sẽ kéo theo những đơn không liên quan, và bảng kết
 * quả đầy nhiễu tới mức không đọc ra điều gì.
 */
class SandboxTourSeeder extends Seeder
{
    /** Dấu để lần seed sau dọn sạch lần trước, không đụng dữ liệu khác. */
    private const TAG = '[sandbox]';

    private User $admin;
    private User $khach;

    /** @var array<int, array<string, string>> */
    private array $bang = [];

    public function run(): void
    {
        $this->admin = User::query()->where('role', 'admin')->firstOrFail();
        $this->khach = User::query()->updateOrCreate(
            ['email' => 'customer@gmail.com'],
            [
                'name' => 'Customer User',
                'password' => bcrypt('customer123'),
                'role' => 'customer',
                'status' => 'active',
            ],
        );

        $this->donDep();

        $this->tourTien();
        $this->tourGhepChuyen();
        $this->tourVongDoi();

        $this->inHuongDan();
    }

    /**
     * Xóa dữ liệu của lần seed trước.
     *
     * Chạy lại seeder này là chuyện thường: sau mỗi buổi thử thì dữ liệu đã bị các nút bấm làm đổi
     * hết, và cách nhanh nhất để về vạch xuất phát là seed lại. Không dọn thì mỗi lần chạy lại đắp
     * thêm một lớp đơn mới lên trên đống cũ.
     */
    private function donDep(): void
    {
        $tours = Tour::withTrashed()->where('is_sandbox', true)->pluck('id');

        if ($tours->isEmpty()) {
            return;
        }

        $donIds = Booking::query()->whereIn('tour_id', $tours)->pluck('id');

        BookingPayment::query()->whereIn('booking_id', $donIds)->delete();
        CustomerContactLog::query()->whereIn('booking_id', $donIds)->delete();
        Booking::query()->whereIn('id', $donIds)->forceDelete();
        GroupBookingRequest::query()->whereIn('tour_id', $tours)->delete();
        TourSchedule::query()->whereIn('tour_id', $tours)->delete();
        Tour::withTrashed()->whereIn('id', $tours)->forceDelete();
    }

    // --- Tour 1: tiền vào tiền ra ------------------------------------------------------------

    private function tourTien(): void
    {
        $tour = $this->taoTour(
            'sandbox-tien-vao-tien-ra',
            'SÂN THỬ 1 — Tiền vào tiền ra',
            'Cọc, trả nốt, thư nhắc, hủy quá hạn, hoàn tiền. Dùng nút tua để đưa chuyến tới từng mốc.',
        );

        // Chuyến để RẤT xa: mọi mốc đều còn ở phía trước, nên nút tua đưa được tới bất kỳ mốc nào.
        $chuyen = $this->taoChuyen($tour, 120);

        $this->don($chuyen, 'Đã cọc 50%, chưa nhận thư nào', tyLeDaThu: 50);
        $this->don($chuyen, 'Đã cọc, đã nhận lá nhắc nhẹ', tyLeDaThu: 50, daNhacNhe: true);
        $this->don($chuyen, 'Đã cọc, đã nhận lá cảnh báo cuối', tyLeDaThu: 50, daNhacNhe: true, daNhacCuoiTruoc: 5);
        $this->don($chuyen, 'Đã trả đủ 100%', tyLeDaThu: 100);
        $this->don($chuyen, 'Sổ ghi 0 đồng (xác nhận tay quên ghi)', tyLeDaThu: 0);
        $this->don($chuyen, 'Chờ thanh toán, chưa cọc', tyLeDaThu: 0, trangThai: 'pending');
        $this->don($chuyen, 'Đơn ĐOÀN còn nợ', tyLeDaThu: 50, laDoan: true);
        $this->don($chuyen, 'Đã hủy, công ty còn nợ tiền hoàn', tyLeDaThu: 50, trangThai: 'cancelled', nghiaVuHoan: true);
    }

    // --- Tour 2: ghép và chuyển chuyến -------------------------------------------------------

    private function tourGhepChuyen(): void
    {
        $tour = $this->taoTour(
            'sandbox-ghep-chuyen-chuyen',
            'SÂN THỬ 2 — Ghép và chuyển chuyến',
            'Hai chuyến cùng tour để ghép vào nhau, và nhật ký liên hệ dựng sẵn để chuyển chuyến bấm được ngay.',
        );

        /*
         * Hai chuyến cách nhau đúng trong giới hạn ghép được (`ScheduleMergeService::MAX_DAY_GAP`),
         * và chuyến đích đi SỚM hơn — đó mới là chiều làm hạn trả nốt nhảy vào quá khứ, tức chiều
         * đáng xem. Ghép sang chuyến muộn hơn thì không có gì xảy ra cả.
         */
        $nguon = $this->taoChuyen($tour, 60);
        $dich = $this->taoChuyen($tour, 59);

        $this->don($nguon, 'Đã cọc — ghép sang chuyến sớm hơn để xem hạn nhảy', tyLeDaThu: 50, coNhatKy: true);
        $this->don($nguon, 'Đã trả đủ — ghép xong không sinh việc gì', tyLeDaThu: 100, coNhatKy: true);
        $this->don($nguon, 'Chưa cọc — ghép chuyến sẽ hủy và mời đặt lại', tyLeDaThu: 0, trangThai: 'pending');
        $this->don($dich, 'Đơn sẵn có ở chuyến đích', tyLeDaThu: 100);
    }

    // --- Tour 3: vòng đời chuyến -------------------------------------------------------------

    private function tourVongDoi(): void
    {
        $tour = $this->taoTour(
            'sandbox-vong-doi-chuyen',
            'SÂN THỬ 3 — Vòng đời chuyến',
            'Hạn chốt danh sách, chốt chạy, thiếu khách, hủy cả chuyến. Tua tới hạn chốt rồi chạy hai lệnh chuyến.',
        );

        // Đủ khách: tua tới hạn chốt rồi chạy `schedules:confirm-ready` thì chuyến chốt chạy.
        $duKhach = $this->taoChuyen($tour, 40, toiThieu: 2);
        $this->don($duKhach, 'Đã trả đủ — góp đủ số khách tối thiểu', tyLeDaThu: 100);
        $this->don($duKhach, 'Đã trả đủ — người thứ hai', tyLeDaThu: 100);

        // Thiếu khách: cùng thao tác nhưng ra thông báo "cần quyết cho chạy hay hủy".
        $thieuKhach = $this->taoChuyen($tour, 41, toiThieu: 8);
        $this->don($thieuKhach, 'Đã trả đủ — nhưng chuyến chưa đủ khách tối thiểu', tyLeDaThu: 100);

        // Chuyến để bấm hủy cả chuyến và chọn phương án cho từng đơn.
        $deHuy = $this->taoChuyen($tour, 42);
        $this->don($deHuy, 'Đã cọc — chờ chọn hoàn tiền hay chuyển chuyến', tyLeDaThu: 50);
        $this->don($deHuy, 'Đã trả đủ — chờ chọn phương án', tyLeDaThu: 100);
        $this->don($deHuy, 'Chưa cọc — sẽ bị hủy thẳng', tyLeDaThu: 0, trangThai: 'pending');
    }

    // --- Bộ dựng ------------------------------------------------------------------------------

    private function taoTour(string $slug, string $title, string $moTa): Tour
    {
        $tour = Tour::query()->create([
            'admin_id' => $this->admin->id,
            'slug' => $slug,
            'title' => $title,
            'description' => $moTa,
            'adult_price' => 5_000_000,
            'child_price' => 3_500_000,
            'infant_price' => 0,
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'start_location' => 'Hà Nội',
            'end_location' => 'Hà Nội',
            'pickup_location' => 'Nhà hát Lớn Hà Nội',
            'status' => 'active',
            'is_featured' => false,
            'is_sandbox' => true,
        ]);

        $this->bang[] = ['loai' => 'TOUR', 'ten' => $title, 'chi_tiet' => $moTa];

        return $tour;
    }

    private function taoChuyen(Tour $tour, int $ngayNua, int $toiThieu = 2): TourSchedule
    {
        $start = Carbon::now()->addDays($ngayNua)->setTime(6, 0);

        $chuyen = TourSchedule::query()->create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2)->setTime(18, 0),
            'booking_deadline' => $start->copy()->subDays((int) config('booking.booking_deadline_days', 3)),
            'max_people' => 20,
            'min_people' => $toiThieu,
            'booked_people' => 0,
        ]);

        $this->bang[] = [
            'loai' => 'CHUYẾN #' . $chuyen->id,
            'ten' => $tour->title,
            'chi_tiet' => 'Khởi hành ' . $start->format('d/m/Y') . ' · tối thiểu ' . $toiThieu . ' khách',
        ];

        return $chuyen;
    }

    /**
     * Một đơn ở đúng tình huống mong muốn.
     *
     * @param  int  $tyLeDaThu  phần trăm giá đơn đã nằm trong sổ; 0 nghĩa là chưa có bút toán nào
     * @param  int|null  $daNhacCuoiTruoc  số ngày trước đây đã gửi lá cảnh báo cuối
     */
    private function don(
        TourSchedule $chuyen,
        string $moTa,
        int $tyLeDaThu,
        string $trangThai = 'confirmed',
        bool $laDoan = false,
        bool $daNhacNhe = false,
        ?int $daNhacCuoiTruoc = null,
        bool $coNhatKy = false,
        bool $nghiaVuHoan = false,
    ): void {
        $tour = $chuyen->tour;
        $tongTien = 2 * (float) $tour->adult_price;
        $daThu = round($tongTien * $tyLeDaThu / 100);

        $don = Booking::query()->create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
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
            'adult_price' => $tour->adult_price,
            'child_price' => $tour->child_price,
            'infant_price' => $tour->infant_price,
            'total_amount' => $tongTien,
            'status' => $trangThai,
            'confirmed_at' => $trangThai === 'confirmed' ? now()->subDays(3) : null,
            'cancelled_at' => $trangThai === 'cancelled' ? now()->subDay() : null,
            'cancel_type' => $trangThai === 'cancelled' ? 'by_company' : null,
            'cancel_reason' => $trangThai === 'cancelled' ? 'Dựng sẵn để thử luồng hoàn tiền.' : null,
            'refund_amount' => $nghiaVuHoan ? $daThu : null,
            'expires_at' => $trangThai === 'pending'
                ? now()->addMinutes((int) config('booking.payment_ttl_minutes', 10))
                : null,
            'cancellation_policy_id' => CancellationPolicy::dangApDung()?->id,
            'note' => self::TAG . ' ' . $moTa,
        ]);

        // Ghi tiền vào SỔ, và chỉ đóng `paid_at` khi thu đủ — đúng cách luồng thật làm.
        if ($daThu > 0) {
            BookingPayment::query()->create([
                'booking_id' => $don->id,
                'kind' => $tyLeDaThu >= 100 ? 'balance' : 'deposit',
                'amount' => $daThu,
                'method' => 'gateway',
                'reference' => 'SANDBOX-' . Str::upper(Str::random(8)),
                'note' => $tyLeDaThu >= 100 ? 'Thanh toán đủ' : 'Đặt cọc giữ chỗ',
                'paid_at' => now()->subDays(3),
            ]);
        }

        if ($tyLeDaThu >= 100) {
            $don->forceFill(['paid_at' => now()->subDays(3)])->save();
        }

        if ($daNhacNhe || $daNhacCuoiTruoc !== null) {
            $don->forceFill([
                'balance_reminder_sent_at' => now()->subDays(($daNhacCuoiTruoc ?? 0) + 5),
                'balance_final_notice_at' => $daNhacCuoiTruoc !== null
                    ? now()->subDays($daNhacCuoiTruoc)
                    : null,
            ])->save();
        }

        if ($laDoan) {
            $yeuCau = GroupBookingRequest::query()->create([
                'public_token' => (string) Str::uuid(),
                'tour_id' => $tour->id,
                'tour_schedule_id' => $chuyen->id,
                'customer_id' => $this->khach->id,
                'contact_name' => 'Công ty ABC',
                'contact_email' => $this->khach->email,
                'contact_phone' => '0901234567',
                'estimated_guests' => 2,
                'quoted_price_per_person' => $tour->adult_price,
                'quoted_free_slots' => 0,
                'status' => GroupRequestStatus::Confirmed,
                'decided_at' => now()->subDays(3),
            ]);

            $don->forceFill(['group_booking_request_id' => $yeuCau->id])->save();
        }

        /*
         * Nhật ký liên hệ dựng sẵn cho đơn dùng để thử CHUYỂN chuyến.
         *
         * Không có bản ghi khách đã đồng ý thì hệ thống chặn thao tác chuyển — đó là luật thật, và
         * đúng. Nhưng người thử tay sẽ đâm vào nó ngay ở bước đầu và tưởng chức năng hỏng, nên với
         * riêng sân thử thì dựng sẵn căn cứ để nút bấm được ngay.
         */
        if ($coNhatKy) {
            CustomerContactLog::query()->create([
                'booking_id' => $don->id,
                'channel' => \App\Enums\ContactChannel::Phone,
                'purpose' => \App\Enums\ContactPurpose::Transfer,
                'outcome' => \App\Enums\ContactOutcome::Agreed,
                'note' => 'Đã gọi hỏi ý khách về việc đổi ngày khởi hành, khách đồng ý.',
                'contacted_by' => $this->admin->id,
                'contacted_at' => now()->subDay(),
            ]);
        }

        if ($trangThai !== 'cancelled') {
            $chuyen->increment('booked_people', 2);
        }

        $this->bang[] = [
            'loai' => 'BK-' . $don->id,
            'ten' => 'Chuyến #' . $chuyen->id,
            'chi_tiet' => $moTa,
        ];
    }

    private function inHuongDan(): void
    {
        $cmd = $this->command;

        if (!$cmd) {
            return;
        }

        $cmd->newLine();
        $cmd->info('Đã dựng 3 tour SÂN THỬ NGHIỆM. Mở màn quản trị → Sân thử nghiệm để bấm.');
        $cmd->line('Đăng nhập khách: customer@gmail.com / customer123');
        $cmd->newLine();

        $cmd->table(['Đối tượng', 'Thuộc', 'Tình huống'], array_map(
            fn (array $d) => [$d['loai'], $d['ten'], $d['chi_tiet']],
            $this->bang,
        ));

        $cmd->newLine();
        $cmd->line('Cách dùng: chọn một chuyến → bấm "Tua tới ..." → bấm lệnh nền → đọc bảng đơn.');
        $cmd->line('Mốc thời gian tính lùi từ lúc seed, nên seed lại trước mỗi buổi thử.');
    }
}
