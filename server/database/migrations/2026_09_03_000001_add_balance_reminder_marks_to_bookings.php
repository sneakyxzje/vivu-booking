<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hai mốc đánh dấu đã nhắc khách trả nốt tiền.
 *
 * Từ khi bán theo cọc, quá hạn trả nốt là mất tiền thật — nên không được để chuyện đó xảy ra với
 * một người chưa từng được nhắc. Hệ thống hiện không nhắc tiền một lần nào: thư nhắc khởi hành chỉ
 * nói điểm đón, giờ tập trung và giấy tờ.
 *
 * Hai cột thay vì một cột đếm, vì hai lần nhắc nói hai chuyện khác nhau và người đọc nhật ký cần
 * phân biệt: lần đầu là lời nhắc bình thường, lần sau là cảnh báo cuối trước khi đơn bị hủy. Có mốc
 * riêng thì trả lời được câu "khách này đã nhận cảnh báo cuối chưa" mà không phải suy từ một con số.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('balance_reminder_sent_at')->nullable()->after('departure_reminder_sent_at');
            $table->timestamp('balance_final_notice_at')->nullable()->after('balance_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['balance_reminder_sent_at', 'balance_final_notice_at']);
        });
    }
};
