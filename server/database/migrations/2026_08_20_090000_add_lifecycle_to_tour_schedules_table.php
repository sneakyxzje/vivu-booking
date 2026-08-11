<?php

use App\Enums\ScheduleStatus;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->dateTime('end_date')->nullable();
            $table->unsignedInteger('min_people')->default(1);
            $table->dateTime('booking_deadline')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('cancelled_reason')->nullable();

            $table->foreignId('merged_into_schedule_id')
                ->nullable()
                ->constrained('tour_schedules')
                ->nullOnDelete();

            $table->boolean('is_private')->default(false);
        });

        /*
         * Đổi status từ:
         * active / inactive / full
         *
         * sang:
         * open / closed / confirmed / in_progress / completed / cancelled
         *
         * Không có điều kiện MySQL ở đây vì SQLite cũng phải
         * thực hiện việc đổi kiểu cột.
         */
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->string('status', 20)
                ->default('open')
                ->change();
        });

        /*
         * Chuyển dữ liệu cũ sang lifecycle mới.
         */
        $schedules = DB::table('tour_schedules')
            ->join('tours', 'tour_schedules.tour_id', '=', 'tours.id')
            ->select(
                'tour_schedules.id',
                'tour_schedules.status',
                'tour_schedules.start_date',
                'tours.number_of_days'
            )
            ->get();

        foreach ($schedules as $schedule) {
            $startDate = $schedule->start_date
                ? Carbon::parse($schedule->start_date)
                : null;

            $hasDeparted = $startDate !== null
                && $startDate->lt(now());

            $newStatus = ScheduleStatus::fromLegacy(
                $schedule->status,
                $hasDeparted
            )->value;

            $endDate = null;

            if ($startDate !== null && $schedule->number_of_days !== null) {
                $endDate = $startDate
                    ->copy()
                    ->addDays(max(0, $schedule->number_of_days - 1));
            }

            $bookingDeadline = null;

            if ($startDate !== null) {
                $bookingDeadline = $startDate
                    ->copy()
                    ->subDays(3);
            }

            DB::table('tour_schedules')
                ->where('id', $schedule->id)
                ->update([
                    'status' => $newStatus,
                    'end_date' => $endDate,
                    'booking_deadline' => $bookingDeadline,
                ]);
        }

        /*
         * Index phục vụ truy vấn lifecycle.
         */
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->index(
                ['tour_id', 'status', 'start_date'],
                'tour_schedules_tour_status_start_index'
            );

            $table->index(
                ['status', 'booking_deadline'],
                'tour_schedules_status_deadline_index'
            );

            $table->index(
                ['guide_id', 'start_date', 'end_date'],
                'tour_schedules_guide_start_end_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->dropIndex('tour_schedules_tour_status_start_index');
            $table->dropIndex('tour_schedules_status_deadline_index');
            $table->dropIndex('tour_schedules_guide_start_end_index');

            $table->dropForeign(['cancelled_by']);
            $table->dropForeign(['merged_into_schedule_id']);

            $table->dropColumn([
                'end_date',
                'min_people',
                'booking_deadline',
                'confirmed_at',
                'cancelled_at',
                'cancelled_by',
                'cancelled_reason',
                'merged_into_schedule_id',
                'is_private',
            ]);
        });

        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'full'])
                ->default('active')
                ->change();
        });
    }
};