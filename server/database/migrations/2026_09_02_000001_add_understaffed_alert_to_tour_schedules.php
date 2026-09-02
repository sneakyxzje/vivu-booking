<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đánh dấu đã báo cho điều hành rằng chuyến này không đủ khách tối thiểu.
 *
 * `ConfirmReadySchedules` chạy mỗi phút. Nó vốn phát hiện được chuyến thiếu khách tại hạn chốt
 * nhưng chỉ in một dòng ra màn hình console rồi đi tiếp — không ai ngồi đọc console, nên trên thực
 * tế `min_people` chưa bao giờ được thực thi: chuyến hai khách vẫn khởi hành như thường.
 *
 * Giờ nó gửi thông báo thật, và cột này giữ cho mỗi chuyến chỉ nhận đúng một lần. Không có nó thì
 * điều hành nhận một thông báo mỗi phút cho tới ngày khởi hành — và học được cách bỏ qua tất cả.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->timestamp('understaffed_alert_sent_at')->nullable()->after('booking_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->dropColumn('understaffed_alert_sent_at');
        });
    }
};
