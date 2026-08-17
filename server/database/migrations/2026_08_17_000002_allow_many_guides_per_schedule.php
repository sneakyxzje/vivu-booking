<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Một chuyến khởi hành được phân công nhiều hướng dẫn viên.
 *
 * Đoàn đông thì một người không kham nổi: điểm danh ở nhiều điểm dừng cùng lúc, khách tách nhóm
 * khi tham quan, xe thứ hai đi cùng tuyến. Cột guide_id đơn lẻ chỉ chứa được một người.
 *
 * Bỏ hẳn cột cũ thay vì giữ lại làm "hướng dẫn viên chính". Hai chỗ cùng lưu một sự thật là khuôn
 * chung của phần lớn lỗi đã gặp ở dự án này: sớm muộn sẽ có đường ghi cập nhật bảng nối mà quên
 * cột, rồi màn hình này hiện một đằng màn hình kia hiện một nẻo.
 *
 * Hệ thống cố ý KHÔNG tự suy ra cần bao nhiêu hướng dẫn viên cho bao nhiêu khách. Tỷ lệ ấy khác
 * nhau theo loại tour, theo tuyến và theo cách công ty vận hành; điều hành tự quyết.
 *
 * Cùng dạng với 2026_07_12_000000, lần đó chuyển phân công từ tour xuống chuyến.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_schedule_guides', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tour_schedule_id')
                ->constrained('tour_schedules')
                ->cascadeOnDelete();

            $table->foreignId('guide_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            // Phân công một người hai lần cho cùng chuyến là vô nghĩa.
            $table->unique(['tour_schedule_id', 'guide_id'], 'uniq_schedule_guide');

            // Kiểm trùng lịch luôn hỏi "người này đang có chuyến nào".
            $table->index('guide_id', 'idx_schedule_guides_guide');
        });

        $daPhanCong = DB::table('tour_schedules')
            ->whereNotNull('guide_id')
            ->orderBy('id')
            ->get(['id', 'guide_id']);

        $now = now();

        foreach ($daPhanCong as $dong) {
            DB::table('tour_schedule_guides')->insert([
                'tour_schedule_id' => $dong->id,
                'guide_id' => $dong->guide_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Chỉ mục cũ dựng trên cột sắp bỏ, phải gỡ trước.
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->dropIndex('idx_schedules_guide_dates');
        });

        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->dropForeign(['guide_id']);
            $table->dropColumn('guide_id');
        });
    }

    public function down(): void
    {
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->foreignId('guide_id')->nullable()->after('tour_id')
                ->constrained('users')->nullOnDelete();
        });

        // Quay về một cột thì chỉ giữ được một người: lấy người được phân công sớm nhất.
        $phanCong = DB::table('tour_schedule_guides')
            ->orderBy('id')
            ->get(['tour_schedule_id', 'guide_id'])
            ->unique('tour_schedule_id');

        foreach ($phanCong as $dong) {
            DB::table('tour_schedules')
                ->where('id', $dong->tour_schedule_id)
                ->update(['guide_id' => $dong->guide_id]);
        }

        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->index(['guide_id', 'start_date', 'end_date'], 'idx_schedules_guide_dates');
        });

        Schema::dropIfExists('tour_schedule_guides');
    }
};
