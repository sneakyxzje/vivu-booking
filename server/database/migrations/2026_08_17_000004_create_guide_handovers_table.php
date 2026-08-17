<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biên bản bàn giao hướng dẫn viên giữa chừng chuyến.
 *
 * Trước đây đổi người dẫn giữa chuyến vẫn làm được - chỉ cần sửa danh sách phân công - nhưng
 * **không để lại vết nào**. Người mới nhận đoàn mà không biết đoàn đang ở đâu, ai đã điểm danh
 * tới chặng nào, có sự cố gì. Và khi có khiếu nại về một chặng thì không tra được lúc ấy ai phụ
 * trách.
 *
 * ---
 *
 * **Cố ý khác thiết kế ở tài liệu 04 mục 4.4.**
 *
 * Tài liệu đề xuất bảng phân công có `effective_from` / `effective_to`, tức mỗi bản ghi là một
 * quãng thời gian phụ trách. Cách đó trả lời được câu "lúc 14h ngày thứ hai ai đang dẫn" bằng
 * một truy vấn, nhưng đổi lại thì `tour_schedule_guides` mang hai nghĩa cùng lúc: vừa là danh
 * sách ai đang phụ trách, vừa là lịch sử. Mọi truy vấn đọc nó - hiện khoảng mười chỗ - đều phải
 * kèm điều kiện thời gian, và quên một chỗ là hướng dẫn viên cũ vẫn điểm danh được sau khi đã
 * bàn giao. Đó đúng là khuôn lỗi đã gặp nhiều lần ở dự án này.
 *
 * Nên tách làm hai, mỗi bảng một nghĩa:
 *
 *   - `tour_schedule_guides` — **ai đang phụ trách**, không có thời gian, đọc thế nào cũng đúng
 *   - `guide_handovers` — **lịch sử bàn giao**, chỉ ghi thêm, không ai đọc nhầm thành phân công
 *
 * Câu "lúc 14h ai dẫn" vẫn trả lời được, bằng cách lần theo lịch sử bàn giao thay vì một truy
 * vấn. Đổi một chút bất tiện khi tra cứu lấy việc không bao giờ đọc nhầm quyền ghi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_handovers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tour_schedule_id')->constrained('tour_schedules')->cascadeOnDelete();

            $table->foreignId('from_guide_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_guide_id')->constrained('users')->cascadeOnDelete();

            /*
             * Thời điểm bàn giao, tách khỏi created_at.
             *
             * Bàn giao xảy ra trên đường, ghi vào hệ thống có khi muộn vài tiếng. Đây là mốc chia
             * trách nhiệm giữa hai người nên phải là thời điểm thật, không phải lúc gõ vào máy.
             */
            $table->dateTime('handed_over_at');

            $table->string('reason', 255);

            /*
             * Tình trạng đoàn tại thời điểm bàn giao, do người giao viết.
             *
             * Đây là phần có giá trị nhất của cả bảng: đoàn đang ở đâu, ai chưa điểm danh, khách
             * nào cần để ý, việc gì đang dở. Bắt buộc nhập, vì bàn giao mà không nói tình trạng
             * thì người mới vẫn phải mò lại từ đầu.
             */
            $table->text('handover_note');

            // Điều hành thực hiện bàn giao. Hướng dẫn viên không tự chuyển đoàn cho người khác.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tour_schedule_id', 'handed_over_at']);
            $table->index('to_guide_id');
            $table->index('from_guide_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_handovers');
    }
};
