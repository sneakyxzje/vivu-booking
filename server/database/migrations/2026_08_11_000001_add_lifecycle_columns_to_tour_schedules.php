<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A01 - Bo sung vong doi chuyen khoi hanh.
 *
 * Tai lieu tham chieu: docs/nghiep-vu/07-thiet-ke-du-lieu.md §1.1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_schedules', function (Blueprint $table) {
            // Thoi diem ket thuc chuyen, tu tinh tu start_date + number_of_days cua tour.
            $table->dateTime('end_date')->nullable()->after('start_date');

            // So khach toi thieu de chuyen duoc chot chay.
            $table->unsignedInteger('min_people')->default(1)->after('max_people');

            // Han chot danh sach. Mac dinh start_date tru 3 ngay.
            $table->dateTime('booking_deadline')->nullable()->after('min_people');

            // Truy vet chot chuyen.
            $table->dateTime('confirmed_at')->nullable()->after('status');

            // Truy vet huy chuyen.
            $table->dateTime('cancelled_at')->nullable()->after('confirmed_at');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete()->after('cancelled_at');
            $table->text('cancelled_reason')->nullable()->after('cancelled_by');

            // Chuyen bi ghep vao chuyen nao (task L01 - Ghep chuyen).
            $table->foreignId('merged_into_schedule_id')
                ->nullable()
                ->constrained('tour_schedules')
                ->nullOnDelete()
                ->after('cancelled_reason');

            // Chuyen rieng danh cho mot doan dat tron, khoa ban le.
            $table->boolean('is_private')->default(false)->after('merged_into_schedule_id');
        });

        $this->widenStatusColumn();

        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->index(['tour_id', 'status', 'start_date'], 'idx_schedules_tour_status_start');
            $table->index(['status', 'booking_deadline'], 'idx_schedules_status_deadline');
            $table->index(['guide_id', 'start_date', 'end_date'], 'idx_schedules_guide_dates');
        });
    }

    public function down(): void
    {
        $this->restoreLegacyStatusColumn();

        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->dropIndex('idx_schedules_guide_dates');
            $table->dropIndex('idx_schedules_status_deadline');
            $table->dropIndex('idx_schedules_tour_status_start');

            $table->dropConstrainedForeignId('merged_into_schedule_id');
            $table->dropConstrainedForeignId('cancelled_by');

            $table->dropColumn([
                'end_date',
                'min_people',
                'booking_deadline',
                'confirmed_at',
                'cancelled_at',
                'cancelled_reason',
                'is_private',
            ]);
        });
    }

    private function widenStatusColumn(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE tour_schedules MODIFY status VARCHAR(20) NOT NULL DEFAULT 'open'");
    }

    private function restoreLegacyStatusColumn(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            UPDATE tour_schedules
            SET status = CASE
                WHEN status = 'open' THEN 'active'
                WHEN status = 'closed' THEN 'full'
                ELSE 'inactive'
            END
        ");

        DB::statement("ALTER TABLE tour_schedules MODIFY status ENUM('active', 'inactive', 'full') NOT NULL DEFAULT 'active'");
    }
};
