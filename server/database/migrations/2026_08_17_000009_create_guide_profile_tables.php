<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hồ sơ năng lực hướng dẫn viên.
 *
 * Trước đây hệ thống chỉ trả lời được **"ai đang rảnh"**, không trả lời được **"ai phù hợp"**.
 * Rảnh là điều kiện cần: một người rảnh nhưng chưa từng đi tuyến Tây Bắc, hoặc thẻ hành nghề hết
 * hạn giữa chuyến, vẫn là lựa chọn sai.
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

            /*
             * Thẻ hành nghề. Đây là thứ duy nhất trong hồ sơ này **chặn** được việc phân công:
             * Luật Du lịch 2017 yêu cầu hướng dẫn viên hành nghề phải có thẻ còn hiệu lực, nên
             * thẻ hết hạn giữa chuyến không phải chuyện ưu tiên cao thấp mà là không được phép.
             *
             * Để nullable: người chưa khai thẻ thì không chặn, chỉ nhắc. Chặn theo dữ liệu trống
             * nghĩa là ngày bật tính năng này lên thì không phân công được cho ai nữa.
             */
            $table->string('card_number', 50)->nullable();
            $table->date('card_expiry')->nullable();

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

            $table->index('card_expiry');
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
