<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\TourSchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * C05 - Đối chiếu booked_people với số chỗ thực tế đang bị chiếm.
 *
 * Vì sao cần: booked_people không còn đơn giản là tổng đơn chưa hủy. Từ khi có quy tắc giữ chỗ
 * sau hạn chốt, nó gồm cả ghế chết, tức đơn đã hủy mà chỗ chưa được trả về kho. Một chỗ lệch
 * không làm đỏ bài kiểm thử nào và cũng không báo lỗi ở đâu, nó chỉ âm thầm khiến hệ thống bán
 * thừa hoặc bán thiếu.
 *
 * Chạy định kỳ để phát hiện sớm. Dùng --fix khi đã xác minh nguyên nhân và muốn nắn lại số liệu.
 */
#[Signature('bookings:check-seat-consistency {--fix : Ghi lại số chỗ đúng thay vì chỉ báo cáo}')]
#[Description('Đối chiếu số chỗ đã bán của từng chuyến với số chỗ thực tế đang bị chiếm')]
class CheckSeatConsistency extends Command
{
    public function handle(): int
    {
        $suaLai = (bool) $this->option('fix');
        $soLech = 0;
        $soDaSua = 0;

        TourSchedule::query()
            ->orderBy('id')
            ->chunkById(100, function ($schedules) use ($suaLai, &$soLech, &$soDaSua) {
                foreach ($schedules as $schedule) {
                    $dangGhi = (int) $schedule->booked_people;
                    $thucTe = $this->occupiedSeats($schedule->id);

                    if ($dangGhi === $thucTe) {
                        continue;
                    }

                    $soLech++;

                    $this->warn(sprintf(
                        'Chuyến #%d lệch: đang ghi %d chỗ, thực tế %d chỗ đang bị chiếm (lệch %+d).',
                        $schedule->id,
                        $dangGhi,
                        $thucTe,
                        $thucTe - $dangGhi,
                    ));

                    if (!$suaLai) {
                        continue;
                    }

                    // Ghi thẳng bằng query builder để không kích hoạt sự kiện model, đây là
                    // thao tác nắn số liệu chứ không phải nghiệp vụ đặt chỗ.
                    DB::table('tour_schedules')
                        ->where('id', $schedule->id)
                        ->update(['booked_people' => $thucTe, 'updated_at' => now()]);

                    $soDaSua++;
                }
            });

        if ($soLech === 0) {
            $this->info('Số chỗ của mọi chuyến đều khớp.');

            return self::SUCCESS;
        }

        $this->newLine();

        if ($suaLai) {
            $this->info("Đã nắn lại {$soDaSua} trên {$soLech} chuyến bị lệch.");

            return self::SUCCESS;
        }

        $this->error("Có {$soLech} chuyến lệch số chỗ. Chạy lại kèm --fix để nắn lại.");

        // Trả mã lỗi để lệnh chạy nền hoặc CI phát hiện được, không im lặng bỏ qua.
        return self::FAILURE;
    }

    /**
     * Số chỗ thực tế đang bị chiếm trên một chuyến.
     *
     * Gồm hai nhóm: đơn còn hiệu lực, và đơn đã hủy sau hạn chốt mà chỗ chưa được trả về kho.
     * Nhóm thứ hai chính là ghế chết, chúng vẫn phải tính vào vì suất đã cam kết với nhà cung cấp.
     */
    private function occupiedSeats(int $scheduleId): int
    {
        return (int) Booking::query()
            ->where('tour_schedule_id', $scheduleId)
            ->where(function ($query) {
                $query->where('status', '!=', 'cancelled')
                    ->orWhere(function ($heldSeats) {
                        $heldSeats->where('status', 'cancelled')
                            ->where('seats_released', false);
                    });
            })
            /*
             * Cộng theo SỐ GHẾ, đúng đơn vị mà `booked_people` đang đếm: em bé đi cùng bố mẹ không
             * chiếm chỗ nào nên không được tính vào đây.
             *
             * Lùi về `guests` khi `seats` bằng 0, giống hệt `Booking::seatsTaken()`. Đây là bản SQL
             * của cùng một luật, cần có vì câu hỏi này đặt ra cho cả một bảng chứ không cho từng
             * bản ghi - cùng lý do `scopeBookable` tồn tại bên cạnh `isBookable()`. Nhánh lùi ấy
             * đón các đơn dựng thẳng vào cơ sở dữ liệu mà không qua luồng đặt chỗ.
             */
            ->sum(DB::raw('CASE WHEN seats > 0 THEN seats ELSE guests END'));
    }
}
