<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hướng dẫn viên xác nhận hoặc từ chối chuyến được phân công.
 *
 * Trước đây điều hành gán xong là coi như xong, hướng dẫn viên chỉ phát hiện ra khi tự mở danh
 * sách tour của mình. Không ai biết họ đã thấy chưa, và không có đường nào để họ nói "hôm đó tôi
 * bận" — họ phải gọi điện, rồi điều hành sửa tay, và lý do không nằm ở đâu cả.
 *
 * ---
 *
 * **Hai cột ở hai chỗ, mỗi chỗ một nghĩa** — cùng cách đã dùng cho bàn giao:
 *
 *   - `tour_schedule_guides.accepted_at` — người này đã xác nhận nhận chuyến chưa. Chưa xác nhận
 *     **vẫn là đã được phân công**: họ nằm trong danh sách, điều hành đang trông vào họ. Nên
 *     quan hệ guides() không phải lọc gì thêm, và mười chỗ đang đọc nó không phải sửa.
 *
 *   - `guide_assignment_declines` — ai đã từ chối chuyến nào, vì sao. Từ chối thì gỡ khỏi danh
 *     sách phân công, nên nếu không có bảng này thì lý do biến mất cùng bản ghi.
 *
 * Gộp trạng thái vào bảng nối thì mọi truy vấn đọc phân công đều phải kèm điều kiện, và quên một
 * chỗ là người đã từ chối vẫn hiện ra như đang phụ trách.
 *
 * ---
 *
 * **Chỉ từ chối được khi chuyến chưa khởi hành.** Đoàn đã lên đường mà người dẫn muốn rút thì đó
 * là bàn giao, không phải từ chối: phải có người nhận trước khi người cũ rời, nếu không đoàn
 * không còn ai. Luồng ấy đã có ở guide_handover_requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_schedule_guides', function (Blueprint $table) {
            $table->dateTime('accepted_at')->nullable()->after('guide_id');
        });

        Schema::create('guide_assignment_declines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tour_schedule_id')->constrained('tour_schedules')->cascadeOnDelete();
            $table->foreignId('guide_id')->constrained('users')->cascadeOnDelete();

            $table->string('reason', 500);
            $table->dateTime('declined_at');

            $table->timestamps();

            $table->index(['tour_schedule_id', 'declined_at']);
            $table->index('guide_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_assignment_declines');

        Schema::table('tour_schedule_guides', function (Blueprint $table) {
            $table->dropColumn('accepted_at');
        });
    }
};
