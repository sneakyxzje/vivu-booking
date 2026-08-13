<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F01 - Yêu cầu thay đổi của khách.
 *
 * Khách đã thanh toán không tự hủy được đơn. Họ gửi yêu cầu, điều hành duyệt rồi hệ thống mới
 * thực thi. Lý do ở docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 5.1: tiền ra khỏi công ty
 * thì phải có người chịu trách nhiệm, không để một cú bấm của khách quyết định.
 *
 * Một bảng dùng chung cho bốn loại yêu cầu, phần khác nhau nằm trong payload. Hiện chỉ loại
 * cancel có luồng xử lý; transfer và change_guests chờ nhóm I và J.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_change_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->string('type', 20);

            // Mô tả yêu cầu, khác nhau theo từng loại. Với cancel thì chỉ cần lý do, nhưng
            // transfer cần chuyến đích và change_guests cần số khách mới.
            $table->json('payload')->nullable();

            /*
             * Mức hoàn chốt tại thời điểm khách bấm gửi, không phải lúc duyệt.
             *
             * Mức hoàn phụ thuộc số giờ còn lại tới khởi hành, mà thời gian thì trôi trong lúc
             * chờ duyệt. Tính lại lúc duyệt nghĩa là khách chịu thiệt vì điều hành xử lý chậm,
             * điều họ không kiểm soát được. Chốt theo lúc gửi và ghi lại ở đây; màn duyệt hiện
             * cả con số tính lại để điều hành thấy chênh lệch và tự quyết nếu bất thường.
             */
            $table->decimal('estimated_refund', 12, 2)->nullable();
            $table->unsignedTinyInteger('estimated_refund_percent')->nullable();

            $table->string('status', 20)->default('pending');

            // Để trống khi khách vãng lai gửi bằng mã tra cứu, khi đó dựa vào requested_email.
            $table->foreignId('requested_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('requested_email')->nullable();
            $table->text('request_note')->nullable();

            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            // Màn duyệt luôn lọc theo trạng thái rồi sắp theo thời gian gửi.
            $table->index(['status', 'created_at']);
            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_change_requests');
    }
};
