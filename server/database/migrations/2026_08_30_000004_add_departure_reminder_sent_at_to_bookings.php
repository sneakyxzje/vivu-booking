<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mốc đã gửi thư nhắc khởi hành.
 *
 * Lệnh nhắc chạy mỗi ngày và quét theo khoảng ngày, nên không có cột này thì cùng một khách nhận
 * một thư giống hệt mỗi ngày cho tới lúc lên xe. Nhận thư thứ ba là bắt đầu bỏ qua, và lần bỏ qua
 * ấy có thể rơi đúng vào thư báo đổi giờ.
 *
 * Là cột thời gian chứ không phải cờ đúng/sai: khi khách gọi lên bảo không nhận được thư, câu hỏi
 * đầu tiên là "hệ thống gửi lúc nào".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('departure_reminder_sent_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('departure_reminder_sent_at');
        });
    }
};
