<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            // Cần biết tours này ai là người tạo (thuộc về admin nào)
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');


            // Tiêu đề tour
            $table->string('title');

            // Slug, sau này nó hiện trên url nhìn cho đẹp
            $table->string('slug')->unique();

            // Mô tả ngắn của tour
            $table->text('description')->nullable();

            // Giá gốc
            $table->decimal('price', 12, 2);

            // Giá giảm ( nếu có )
            $table->decimal('discount_price', 12, 2)->nullable();

            // Thumbnail của tour
            $table->string('thumbnail')->nullable();

            // Column này lưu thành 2 cột days và nights. Sau filter ví dụ 3 ngày 2 đêm cho dễ
            $table->integer('number_of_days')->default(1);
            $table->integer('number_of_nights')->default(0);

            // Điểm bắt đầu tour
            $table->string('start_location');

            // Điểm kết thúc tour
            $table->string('end_location')->nullable();

            // Kiểm tra tour có nổi bật không, có thì đưa lên trang chủ ( tạm thời là false )
            $table->boolean('is_featured')->default(false);

            $table->enum('status', ['pending', 'active', 'inactive'])->default('pending');

            $table->timestamps();

            // Index cho các trường hay dùng để tìm kiếm, lọc tour
            $table->index(['admin_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
