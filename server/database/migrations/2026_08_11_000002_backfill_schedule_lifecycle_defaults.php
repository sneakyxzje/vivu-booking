<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A02 - Backfill du lieu vong doi cho cac chuyen da ton tai.
 *
 * Dung raw SQL thay vi Eloquent de migration khong phu thuoc vao trang thai model
 * tai thoi diem chay. Neu chay lai nhieu lan thi WHERE IS NULL dam bao idempotent.
 *
 * Tai lieu tham chieu: docs/nghiep-vu/07-thiet-ke-du-lieu.md §1.1
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
        DB::statement("
            UPDATE tour_schedules ts
            JOIN tours t ON t.id = ts.tour_id
            SET ts.end_date = DATE_ADD(ts.start_date, INTERVAL (GREATEST(t.number_of_days, 1) - 1) DAY)
            WHERE ts.end_date IS NULL
        ");

        DB::statement("
            UPDATE tour_schedules
            SET booking_deadline = DATE_SUB(start_date, INTERVAL 3 DAY)
            WHERE booking_deadline IS NULL
        ");

        DB::statement("
            UPDATE tour_schedules
            SET status = CASE
                WHEN status = 'active' AND start_date < NOW() THEN 'completed'
                WHEN status = 'active' THEN 'open'
                WHEN status = 'full' THEN 'closed'
                WHEN status = 'inactive' AND start_date < NOW() THEN 'completed'
                WHEN status = 'inactive' THEN 'cancelled'
                WHEN status = 'open' AND start_date < NOW() THEN 'completed'
                ELSE status
            END
        ");
    }

    private function backfillSqlite(): void
    {
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
            SET status = CASE
                WHEN status = 'active' AND datetime(start_date) < datetime('now') THEN 'completed'
                WHEN status = 'active' THEN 'open'
                WHEN status = 'full' THEN 'closed'
                WHEN status = 'inactive' AND datetime(start_date) < datetime('now') THEN 'completed'
                WHEN status = 'inactive' THEN 'cancelled'
                WHEN status = 'open' AND datetime(start_date) < datetime('now') THEN 'completed'
                ELSE status
            END
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE tour_schedules
            SET end_date = NULL,
                booking_deadline = NULL,
                status = CASE
                    WHEN status = 'open' THEN 'active'
                    WHEN status = 'closed' THEN 'full'
                    ELSE 'inactive'
                END
        ");
    }
};
