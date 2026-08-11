<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A02 — Backfill dữ liệu vòng đời cho các chuyến đã tồn tại.
 *
 * Dùng raw SQL thay vì Eloquent để migration không phụ thuộc vào trạng thái model
 * tại thời điểm chạy. Nếu chạy lại nhiều lần thì WHERE IS NULL đảm bảo idempotent.
 *
 * Tài liệu tham chiếu: docs/nghiep-vu/07-thiet-ke-du-lieu.md §1.1
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->backfillSqlite();
        } else {
            $this->backfillMysql();
        }
    }

    private function backfillMysql(): void
    {
        // end_date = start_date + (tours.number_of_days - 1) ngày
        DB::statement("
            UPDATE tour_schedules ts
            JOIN tours t ON t.id = ts.tour_id
            SET ts.end_date = DATE_ADD(ts.start_date, INTERVAL (GREATEST(t.number_of_days, 1) - 1) DAY)
            WHERE ts.end_date IS NULL
        ");

        // booking_deadline = start_date - 3 ngày
        DB::statement("
            UPDATE tour_schedules
            SET booking_deadline = DATE_SUB(start_date, INTERVAL 3 DAY)
            WHERE booking_deadline IS NULL
        ");

        // status: completed nếu start_date đã qua, open nếu còn
        DB::statement("
            UPDATE tour_schedules
            SET status = CASE
                WHEN start_date < NOW() THEN 'completed'
                ELSE 'open'
            END
            WHERE status = 'open' AND start_date < NOW()
        ");
    }

    private function backfillSqlite(): void
    {
        // SQLite không hỗ trợ JOIN trong UPDATE, phải dùng subquery.
        DB::statement("
            UPDATE tour_schedules
            SET end_date = datetime(
                start_date,
                '+' || (
                    SELECT MAX(number_of_days, 1) - 1
                    FROM tours
                    WHERE tours.id = tour_schedules.tour_id
                ) || ' days'
            )
            WHERE end_date IS NULL
        ");

        DB::statement("
            UPDATE tour_schedules
            SET booking_deadline = datetime(start_date, '-3 days')
            WHERE booking_deadline IS NULL
        ");

        DB::statement("
            UPDATE tour_schedules
            SET status = 'completed'
            WHERE status = 'open' AND datetime(start_date) < datetime('now')
        ");
    }

    public function down(): void
    {
        // Reset về null để rollback sạch.
        DB::statement("UPDATE tour_schedules SET end_date = NULL, booking_deadline = NULL, status = 'open'");
    }
};
