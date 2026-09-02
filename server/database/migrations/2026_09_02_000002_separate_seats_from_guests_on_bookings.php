<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tách SỐ GHẾ khỏi SỐ KHÁCH trên đơn đặt tour.
 *
 * `PassengerPolicyService` định nghĩa em bé dưới hai tuổi là khách "không chiếm ghế riêng" — các
 * cháu ngồi cùng bố mẹ trên xe và ngủ chung giường. Nhưng luồng đặt chỗ lại cộng cả `infant_count`
 * vào `guests` rồi trừ thẳng vào `booked_people`, tức mỗi em bé vẫn ăn một chỗ của chuyến. Xe 30
 * chỗ nhận đoàn có 5 em bé thì chỉ bán được 25 chỗ có tiền, và con số báo cho nhà xe cũng lệch.
 *
 * Từ đây hai câu hỏi tách hẳn nhau:
 *
 *   - `guests` — BAO NHIÊU NGƯỜI đi. Danh sách đoàn, điểm danh, khai báo lưu trú đọc cột này.
 *   - `seats`  — BAO NHIÊU CHỖ bị chiếm. Kho chỗ của chuyến đọc cột này.
 *
 * Chúng bằng nhau ở mọi đơn không có em bé, tức gần hết. Đúng vì thế mà việc trộn hai khái niệm
 * vào một cột đứng được lâu đến vậy.
 *
 * Migration này còn NẮN LẠI `booked_people` của mọi chuyến, vì số đang lưu được cộng theo luật cũ.
 * Không nắn thì mọi chuyến từng có em bé sẽ lệch vĩnh viễn, và `bookings:check-seat-consistency`
 * báo đỏ ngay lần chạy đầu tiên sau khi cập nhật.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('seats')->default(0)->after('guests');
        });

        /*
         * Ghế = người lớn + trẻ em. Trẻ em CÓ chiếm ghế: các cháu ngồi ghế riêng và tính suất ăn,
         * chỉ có giá là rẻ hơn. Chỉ em bé mới không chiếm.
         *
         * Đơn đoàn ghi cả đoàn vào `adult_count` (giá đoàn không chia loại khách), nên công thức
         * này trả về đúng số người của đoàn.
         *
         * Đơn cũ không tách loại khách - `adult_count` và `child_count` đều 0 - thì lùi về `guests`,
         * thà giữ nguyên cách tính cũ còn hơn đặt chúng về 0 và mở ra một chuyến bán quá chỗ.
         */
        DB::table('bookings')->update([
            'seats' => DB::raw('CASE WHEN (COALESCE(adult_count, 0) + COALESCE(child_count, 0)) > 0
                THEN COALESCE(adult_count, 0) + COALESCE(child_count, 0)
                ELSE COALESCE(guests, 0) END'),
        ]);

        $this->nanLaiSoChoDaBan();
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('seats');
        });

        $this->nanLaiSoChoDaBanTheoGuests();
    }

    /**
     * Tính lại `booked_people` theo số ghế thực tế đang bị chiếm.
     *
     * Cùng công thức với `CheckSeatConsistency::occupiedSeats()`: đơn còn hiệu lực, cộng với đơn đã
     * hủy sau hạn chốt mà chỗ chưa được trả về kho (ghế chết - suất đã cam kết với nhà cung cấp).
     */
    private function nanLaiSoChoDaBan(): void
    {
        $this->apDungSoChoDaBan('seats');
    }

    private function nanLaiSoChoDaBanTheoGuests(): void
    {
        $this->apDungSoChoDaBan('guests');
    }

    private function apDungSoChoDaBan(string $cot): void
    {
        DB::table('tour_schedules')->orderBy('id')->select('id')->chunk(200, function ($schedules) use ($cot) {
            foreach ($schedules as $schedule) {
                $daChiem = (int) DB::table('bookings')
                    ->where('tour_schedule_id', $schedule->id)
                    ->where(function ($query) {
                        $query->where('status', '!=', 'cancelled')
                            ->orWhere(function ($gheChet) {
                                $gheChet->where('status', 'cancelled')
                                    ->where('seats_released', false);
                            });
                    })
                    ->sum($cot);

                DB::table('tour_schedules')
                    ->where('id', $schedule->id)
                    ->update(['booked_people' => $daChiem]);
            }
        });
    }
};
