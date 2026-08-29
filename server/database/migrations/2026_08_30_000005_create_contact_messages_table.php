<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hộp thư liên hệ.
 *
 * Trang `/contact` cho tới nay là chữ tĩnh: một số điện thoại, một địa chỉ email, không có ô nào
 * để gõ. Ai muốn hỏi phải tự mở ứng dụng thư của mình ra — và phần lớn thì thôi không hỏi nữa.
 *
 * `status` chỉ có hai giá trị, `new` và `handled`. Thêm "đang xử lý" nghe hợp lý nhưng không
 * tương ứng với thao tác nào có thật: người trực hộp thư đọc, gọi hoặc trả lời thư, rồi đánh dấu
 * xong. Không có bước ở giữa để bấm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('subject')->nullable();
            $table->text('message');

            $table->string('status', 20)->default('new');
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('handling_note', 500)->nullable();

            $table->timestamps();

            // Hộp thư mở ra là lọc theo trạng thái, sắp theo thời gian.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
