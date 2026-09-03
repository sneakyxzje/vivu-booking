<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mốc khách xác nhận đã đọc điều khoản, ghi tại thời điểm đặt.
 *
 * ## Vì sao cần một cột, không chỉ một ô tích trên giao diện
 *
 * Đơn đã chép sẵn `cancellation_policy_id` vào chính nó lúc đặt, nên hệ thống luôn biết **điều
 * khoản nào** có hiệu lực với đơn ấy. Thứ còn thiếu là bằng chứng rằng khách đã được cho xem nó
 * trước khi trả tiền.
 *
 * Đó chính là chỗ mọi tranh chấp hoàn tiền bắt đầu: khách nói "không ai bảo tôi mất 30%", và hệ
 * thống không có gì để trả lời. Một ô tích chỉ tồn tại trong trình duyệt thì không phải bằng chứng
 * — nó biến mất ngay khi trang đóng lại.
 *
 * Nghị định 52/2013 về thương mại điện tử cũng đòi đúng điều này: người bán phải để khách rà soát
 * và xác nhận các điều khoản trước khi giao kết, và phải lưu được việc đó.
 *
 * ## Vì sao `nullable`
 *
 * Đơn đặt trước migration này không có mốc để điền, và bịa ra một mốc là dựng bằng chứng cho một
 * việc chưa từng xảy ra. Để trống nói đúng sự thật: "không rõ". Đơn đoàn cũng để trống — ở luồng
 * ấy khách ký hợp đồng giấy, và chữ ký mới là bằng chứng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('terms_accepted_at')->nullable()->after('cancellation_policy_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('terms_accepted_at');
        });
    }
};
