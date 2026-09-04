<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kho tri thức của chatbot: từng đoạn văn kèm vector ngữ nghĩa.
 *
 * Số chỗ và trạng thái chuyến cố ý KHÔNG nằm ở đây — chúng đổi từng phút còn kho
 * này chỉ dựng lại theo đợt, nên nhúng vào là có ngày chatbot mời khách vào
 * chuyến đã đầy. Những con số đó hỏi thẳng cơ sở dữ liệu, xem AdvisorTools.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_knowledge_chunks', function (Blueprint $table) {
            $table->id();

            // Danh tính bền của một đoạn ("tour:12:ngay-3"), để dựng lại kho là
            // ghi đè đúng chỗ thay vì xóa sạch rồi tạo mới. 191 ký tự vì cột này
            // có khóa duy nhất, 255 với utf8mb4 vượt giới hạn khóa của MySQL cũ.
            $table->string('chunk_key', 191)->unique();

            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('title');
            $table->text('content');

            // Nội dung không đổi thì không mã hóa lại — mã hóa là lời gọi mất tiền.
            $table->char('content_hash', 64);

            // Vector đóng gói nhị phân rồi base64: gọn hơn JSON một nửa, và cột
            // text chạy giống nhau trên MySQL lẫn SQLite lúc kiểm thử.
            $table->text('embedding')->nullable();

            // Đổi model embedding là đổi số chiều; Retriever đọc cột này để bỏ qua
            // vector cũ thay vì so sánh ra một con số vô nghĩa.
            $table->unsignedSmallInteger('dimensions')->nullable();

            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_chunks');
    }
};
