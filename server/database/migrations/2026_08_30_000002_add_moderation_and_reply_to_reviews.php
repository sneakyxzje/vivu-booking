<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kiểm duyệt và trả lời đánh giá.
 *
 * ## Vì sao cần kiểm duyệt
 *
 * Đánh giá là chữ của người ngoài in trên trang bán hàng của công ty. Không có bước duyệt thì một
 * dòng chửi bới, một số điện thoại quảng cáo, hay một cáo buộc sai sự thật lên thẳng trang tour và
 * ở đó cho tới khi có người tình cờ nhìn thấy.
 *
 * Mặc định là `pending`: đánh giá mới chưa hiện với người khác cho tới khi điều hành duyệt. Người
 * viết vẫn thấy đánh giá của chính mình kèm nhãn "đang chờ duyệt" — không nói gì thì họ tưởng bấm
 * gửi không ăn và gửi lại.
 *
 * Đánh giá đã có từ trước được đặt thẳng là `approved`. Chuyển tất cả về `pending` là làm biến mất
 * toàn bộ đánh giá đang hiện trên trang, tức dùng một lần chạy migration để trừng phạt những người
 * đã viết đúng luật lúc đó.
 *
 * ## Vì sao cần chỗ trả lời
 *
 * Một lời phàn nàn không có câu trả lời bên dưới là lời cuối cùng về chuyến đi đó. Trả lời tử tế
 * cho một đánh giá 2 sao thường thuyết phục người đọc hơn cả mười đánh giá 5 sao.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('comment');
            $table->timestamp('moderated_at')->nullable()->after('status');
            $table->foreignId('moderated_by')->nullable()->after('moderated_at')
                ->constrained('users')->nullOnDelete();
            // Lý do từ chối: người viết có quyền biết vì sao chữ của họ không được đăng.
            $table->string('moderation_note', 500)->nullable()->after('moderated_by');

            $table->text('reply')->nullable()->after('moderation_note');
            $table->timestamp('replied_at')->nullable()->after('reply');
            $table->foreignId('replied_by')->nullable()->after('replied_at')
                ->constrained('users')->nullOnDelete();

            // Trang tour lọc theo trạng thái ở mọi lượt truy cập; hàng đợi duyệt lọc theo
            // trạng thái trên toàn bảng.
            $table->index(['tour_id', 'status']);
            $table->index('status');
        });

        DB::table('reviews')->update([
            'status' => 'approved',
            'moderated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['tour_id', 'status']);
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropConstrainedForeignId('replied_by');
            $table->dropColumn([
                'status',
                'moderated_at',
                'moderation_note',
                'reply',
                'replied_at',
            ]);
        });
    }
};
