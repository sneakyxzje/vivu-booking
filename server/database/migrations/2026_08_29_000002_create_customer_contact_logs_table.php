<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhật ký liên hệ khách, và ràng buộc chuyển chuyến phải dựa vào một cuộc liên hệ cụ thể.
 *
 * ## Vì sao cần một bảng riêng
 *
 * Chuyển chuyến là việc động tới ngày đi của người khác. Trước đây điều hành gõ một dòng lý do rồi
 * bấm chuyển; hệ thống không biết khách có được hỏi hay không, và sau này cũng không ai tra lại
 * được. Khi khách nói "tôi có đồng ý đâu" thì công ty không có gì để đưa ra.
 *
 * Nhét thêm cột `da_bao_khach` vào `booking_transfers` thì rẻ hơn, nhưng nó chỉ lưu được một chữ
 * "rồi" - không có ai gọi, gọi lúc nào, khách nói gì. Mà giá trị của bản ghi này nằm đúng ở mấy
 * thứ đó.
 *
 * ## Vì sao chuyển chuyến trỏ tới ĐÚNG một bản ghi
 *
 * `booking_transfers.contact_log_id` chỉ ra chính cuộc trao đổi làm căn cứ, chứ không chỉ kiểm
 * "đơn này từng có ai đó đồng ý". Một lần khách gật đầu là gật cho một phương án cụ thể; nếu chỉ
 * kiểm sự tồn tại thì lần chuyển thứ hai, sang một chuyến khác hẳn, vẫn mượn được cái gật đầu cũ.
 *
 * Cột để nullable vì luồng hủy cả chuyến cũng dời khách qua đây và ở đó không có cuộc gọi riêng
 * cho từng đơn - chuyến gốc không còn tồn tại, khách nhận thư báo hủy kèm lựa chọn hoàn tiền. Ràng
 * buộc nằm ở tầng dịch vụ, nơi phân biệt được hai tình huống ấy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_contact_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();

            $table->string('channel', 20);
            $table->string('purpose', 30);
            $table->string('outcome', 20);

            // Khách nói gì. Bắt buộc ở tầng validate - một bản ghi không có nội dung thì chỉ chứng
            // minh được là có người bấm nút, không chứng minh được là có người gọi.
            $table->text('note');

            $table->foreignId('contacted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('contacted_at');

            $table->timestamps();

            $table->index(['booking_id', 'contacted_at']);
        });

        Schema::table('booking_transfers', function (Blueprint $table) {
            $table->foreignId('contact_log_id')->nullable()->after('initiated_by')
                ->constrained('customer_contact_logs')->nullOnDelete();

            $table->string('reason_category', 30)->nullable()->after('contact_log_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_log_id');
            $table->dropColumn('reason_category');
        });

        Schema::dropIfExists('customer_contact_logs');
    }
};
