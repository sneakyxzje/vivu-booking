<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhật ký hội thoại với chatbot.
 *
 * Lịch sử nằm ở máy chủ chứ không để trình duyệt gửi kèm: lượt của trợ lý mà do
 * trình duyệt cung cấp thì ai cũng bịa được, và bịa được lượt của trợ lý nghĩa là
 * dựng sẵn được một tiền lệ về giá rồi bảo model làm theo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();

            // Ngẫu nhiên, không phải số thứ tự: cùng lý do với mã tra cứu đơn hàng.
            $table->uuid('conversation_token')->index();

            // Khách vãng lai vẫn hỏi được nên cột này rỗng được.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('role', 16);
            $table->text('content');

            // Công cụ đã chạy trong lượt này — cần khi khách báo "chatbot nói sai giá".
            $table->json('tool_calls')->nullable();

            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
