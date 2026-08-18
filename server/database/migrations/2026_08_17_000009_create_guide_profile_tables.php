<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hồ sơ năng lực hướng dẫn viên.
 *
 * Trước đây hệ thống chỉ trả lời được **"ai đang rảnh"**, không trả lời được **"ai phù hợp"**.
 * Rảnh là điều kiện cần chứ chưa đủ: một người rảnh nhưng chưa từng đi tuyến Tây Bắc, hoặc quen
 * dẫn đoàn nghỉ dưỡng mà bị xếp tour leo núi, vẫn là lựa chọn kém hơn người khác.
 *
 * Hồ sơ này **không chặn ai**. Nó chỉ xếp thứ tự và nói ra lý do; luật chặn duy nhất vẫn là
 * chống trùng lịch. Ai hợp hơn ai là chuyện điều hành cân, hệ thống chỉ đưa thông tin lên bàn.
 *
 * Bảng riêng chứ không thêm cột vào `users`, vì `users` dùng chung cho ba vai trò - khách hàng và
 * điều hành sẽ mang theo một loạt cột không bao giờ có giá trị.
 *
 * Chuyên môn để ở bảng nối với `categories` chứ không phải chuỗi tự do: có thế mới so khớp được
 * với loại hình của tour bằng phép giao tập hợp. Gõ tay thì "Biển đảo" và "biển đảo" thành hai thứ
 * khác nhau và không bao giờ khớp.
 *
 * Ngôn ngữ và vùng tuyến vẫn là danh sách tự do, vì không có danh mục sẵn để chọn - và cũng đúng
 * với thực tế: tuyến quen của mỗi người mỗi khác, không gói vào một bảng cứng được.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            // Danh sách, lưu JSON vì chỉ đọc cả cụm chứ không truy vấn theo từng phần tử.
            $table->json('languages')->nullable();
            $table->json('regions')->nullable();

            /*
             * Sức dẫn tối đa - **cảnh báo, không chặn**.
             *
             * Đoàn bao nhiêu khách thì cần mấy người dẫn là quyết định của điều hành, hệ thống
             * không suy ra hộ. Ở đây cũng vậy: vượt sức dẫn thì nói ra, còn bấm hay không là việc
             * của người xếp - họ có thể xếp thêm người thứ hai mà hệ thống không biết trước.
             */
            $table->unsignedInteger('max_group_size')->nullable();

            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('guide_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_categories');
        Schema::dropIfExists('guide_profiles');
    }
};
