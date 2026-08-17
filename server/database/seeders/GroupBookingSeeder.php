<?php

namespace Database\Seeders;

use App\Models\GroupBookingRequest;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\GroupBookingService;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu mẫu cho booking đoàn - mỗi bản ghi minh họa một chặng của đường ống.
 *
 * Đi qua GroupBookingService thay vì insert thẳng, để dữ liệu mẫu cũng phải chui qua đúng các
 * luật mà dữ liệu thật phải chịu - seeder mà lách luật thì demo trơn tru nhưng nói dối.
 */
class GroupBookingSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(GroupBookingService::class);
        $admin = User::query()->where('role', 'admin')->first();

        // Chuyến mở bán còn xa ngày và đủ rộng cho một đoàn.
        $chuyen = TourSchedule::query()
            ->where('status', 'open')
            ->whereDate('start_date', '>', now()->addDays(10))
            ->orderByRaw('max_people - booked_people desc')
            ->first();

        if (!$chuyen || !$admin) {
            $this->command?->warn('Bỏ qua GroupBookingSeeder: thiếu chuyến mở bán hoặc tài khoản admin.');

            return;
        }

        if (GroupBookingRequest::query()->exists()) {
            return; // Idempotent: chạy lại không nhân đôi dữ liệu.
        }

        // 1. Yêu cầu mới gửi, chờ báo giá.
        $service->submit([
            'tour_id' => $chuyen->tour_id,
            'tour_schedule_id' => $chuyen->id,
            'contact_name' => 'Lê Thị Hành Chính',
            'contact_email' => 'hanhchinh@thepmienbac.vn',
            'contact_phone' => '0913005001',
            'estimated_guests' => 28,
            'company_name' => 'Công ty Thép Miền Bắc',
            'tax_code' => '0101234567',
            'invoice_address' => 'Số 5 Nguyễn Trãi, Hà Nội',
            'note' => 'Đoàn nghỉ mát công đoàn, muốn có 1 bữa gala tối.',
        ]);

        // 2. Đã báo giá, đang chờ khách trả lời.
        $daBaoGia = $service->submit([
            'tour_id' => $chuyen->tour_id,
            'tour_schedule_id' => $chuyen->id,
            'contact_name' => 'Phạm Văn Tổ Chức',
            'contact_email' => 'events@vietsoft.vn',
            'contact_phone' => '0913005002',
            'estimated_guests' => 15,
            'company_name' => 'VietSoft',
            'tax_code' => '0109876543',
        ]);
        $service->quote($daBaoGia, 1_750_000, 1, now()->addDays(7), 'Giá đoàn, đã gồm trưởng đoàn miễn phí.', $admin);

        // 3. Đã chốt thành đơn và cọc 30% - để thấy sổ giao dịch có dòng.
        $daChot = $service->submit([
            'tour_id' => $chuyen->tour_id,
            'tour_schedule_id' => $chuyen->id,
            'contact_name' => 'Ngô Thị Kế Toán',
            'contact_email' => 'ketoan@bachmoc.vn',
            'contact_phone' => '0913005003',
            'estimated_guests' => 12,
            'company_name' => 'Nội thất Bạch Mộc',
            'tax_code' => '0305556677',
        ]);
        $service->quote($daChot, 1_800_000, 1, now()->addDays(7), null, $admin);
        $booking = $service->confirm($daChot, 12, $admin);
        $service->recordPayment(
            $booking,
            'deposit',
            round((float) $booking->total_amount * 0.3),
            'bank_transfer',
            'CK-' . now()->format('ymd') . '-001',
            'Cọc 30% khi chốt đoàn',
            $admin,
        );

        $this->command?->info('Đã tạo 3 yêu cầu đoàn: chờ báo giá / đã báo giá / đã chốt kèm cọc 30%.');
    }
}
