<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Models\TourSchedule;
use App\Services\BookingFinalizationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * D03 - Chốt đơn của các chuyến đã kết thúc.
 *
 * Chạy sau schedules:advance-status. Chuyến chuyển sang 'completed' xong thì đơn vẫn còn treo
 * ở 'confirmed', lệnh này là bước đóng nốt vòng đời của đơn.
 */
#[Signature('bookings:finalize-completed')]
#[Description('Chốt đơn của chuyến đã kết thúc thành đã hoàn thành hoặc khách không có mặt')]
class FinalizeCompletedBookings extends Command
{
    public function __construct(
        private readonly BookingFinalizationService $finalization,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tongHoanThanh = 0;
        $tongVangMat = 0;

        $this->candidates()->chunkById(100, function ($schedules) use (&$tongHoanThanh, &$tongVangMat) {
            foreach ($schedules as $schedule) {
                $ketQua = $this->finalization->finalizeSchedule($schedule);

                if ($ketQua['completed'] === 0 && $ketQua['no_show'] === 0) {
                    continue;
                }

                $tongHoanThanh += $ketQua['completed'];
                $tongVangMat += $ketQua['no_show'];

                $this->line(sprintf(
                    'Chuyến #%d: chốt %d đơn đã hoàn thành, %d đơn khách không có mặt.',
                    $schedule->id,
                    $ketQua['completed'],
                    $ketQua['no_show'],
                ));
            }
        });

        $this->info("Đã chốt {$tongHoanThanh} đơn hoàn thành và {$tongVangMat} đơn khách không có mặt.");

        return self::SUCCESS;
    }

    /**
     * Chuyến cần xét.
     *
     * Không lọc riêng status = 'completed'. Trạng thái lưu trong cơ sở dữ liệu có thể chậm hơn
     * đồng hồ khi tác vụ nền bị dừng một lúc, và lúc đó đơn của chuyến đã đi xong sẽ nằm treo
     * mãi. Lấy rộng theo end_date rồi để dịch vụ đối chiếu trạng thái thực tế.
     */
    private function candidates()
    {
        return TourSchedule::query()
            ->with('tour:id,number_of_days')
            ->whereIn('status', [
                ScheduleStatus::Confirmed->value,
                ScheduleStatus::InProgress->value,
                ScheduleStatus::Completed->value,
            ])
            ->whereHas('bookings', function ($query) {
                $query->whereIn('status', BookingStatus::paidValues());
            })
            ->orderBy('id');
    }
}
