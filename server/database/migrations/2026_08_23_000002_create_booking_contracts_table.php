<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Q - Hợp đồng du lịch.
 *
 * Hội đồng hỏi thẳng: mua tour trọn gói thì hợp đồng đâu. Toàn bộ dữ liệu để in ra hợp đồng đã
 * nằm sẵn trong hệ thống - khách, tour, lịch trình, giá, chính sách hủy - nhưng chưa ai ghép
 * chúng lại thành một văn bản.
 *
 * Bảng này chỉ giữ phần KHÔNG suy ra được từ đơn hàng:
 *
 *   - **Số hợp đồng**, cấp một lần rồi cố định. Đây là lý do chính phải có bảng: số hợp đồng mà
 *     tính lại mỗi lần mở là số không dùng được, vì khách đang cầm bản in ghi số cũ.
 *   - **Thời điểm cấp** và người cấp.
 *   - **Thời điểm ký**, điền khi khách ký xong.
 *
 * Mọi thứ còn lại đọc từ đơn tại thời điểm in. Chép lại giá, chép lại lịch trình vào đây thì
 * thành hai nguồn sự thật cho cùng một con số, và đó là chỗ chúng lệch nhau.
 *
 * Một đơn một hợp đồng, ràng bằng khóa duy nhất trên `booking_id`. Cần cấp lại thì in lại chính
 * bản ấy, không sinh số mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_contracts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();

            /*
             * Dạng HD-2026-0001. Duy nhất toàn bảng, không chỉ trong năm: chỉ mục duy nhất trên
             * một cột là thứ cơ sở dữ liệu bảo đảm được, còn "duy nhất trong phạm vi năm" thì
             * phải tự ghép cột năm vào và dễ quên.
             */
            $table->string('contract_number', 30)->unique();

            $table->dateTime('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            // Khách ký xong thì điền. Chưa ký không phải lỗi - hợp đồng vừa in ra là chưa ký.
            $table->dateTime('signed_at')->nullable();
            $table->string('signed_note', 500)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_contracts');
    }
};
