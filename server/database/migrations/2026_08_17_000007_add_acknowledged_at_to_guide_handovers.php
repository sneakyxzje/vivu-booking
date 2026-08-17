<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Người nhận đoàn đã đọc biên bản bàn giao chưa.
 *
 * Điều hành có toàn quyền chuyển và chuyển ngay - bắt chờ người nhận xác nhận thì trong lúc chờ,
 * người cũ đã rời mà người mới chưa nhận, đoàn không ai chịu trách nhiệm. Đúng cái khoảng trống
 * mà luật "không bỏ rơi đoàn" sinh ra để bịt.
 *
 * Nhưng rủi ro thật không phải "họ từ chối" mà là **họ không biết**. Trên màn hình ghi đã bàn
 * giao, ngoài đời người kia đang trong hang không có sóng, chưa hề hay biết mình vừa nhận thêm
 * một đoàn. Đó cùng loại lỗi với việc hủy chuyến mà không đụng tới đơn của khách: màn hình nói
 * xong, thực tế thì không.
 *
 * Cột này không chặn gì cả. Nó biến câu hỏi "nó biết chưa nhỉ" từ một cuộc gọi thành một dòng
 * trên màn hình.
 *
 * Không có nút từ chối nhận: từ chối một đoàn đang trên đường không phải là trả lại mà là xin
 * được thay tiếp, và luồng đó đã có sẵn ở guide_handover_requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_handovers', function (Blueprint $table) {
            $table->dateTime('acknowledged_at')->nullable()->after('is_emergency_cover');
        });
    }

    public function down(): void
    {
        Schema::table('guide_handovers', function (Blueprint $table) {
            $table->dropColumn('acknowledged_at');
        });
    }
};
