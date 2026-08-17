<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Booking theo đoàn - điểm 14 của hội đồng.
 *
 * Đoàn khác khách lẻ ở toàn bộ quy trình, không chỉ ở số lượng. Khách lẻ thấy giá trên web, bấm
 * đặt, trả đủ qua cổng trong mười phút. Một công ty đưa 40 nhân viên đi thì không ai làm thế:
 * kế toán của họ cần báo giá để trình sếp duyệt, giá phải thương lượng, tiền chuyển khoản làm
 * nhiều đợt, và lúc gửi yêu cầu họ còn chưa biết chính xác những ai sẽ đi.
 *
 * Vì vậy đơn đoàn đi một đường ống riêng: **yêu cầu → báo giá → chốt**, và chỉ ở bước chốt mới
 * sinh ra một `Booking` thật sự chiếm chỗ. Trước đó là thương lượng, chưa cam kết gì với ai nên
 * chưa được giữ chỗ của chuyến.
 *
 * ## Vì sao có bảng sổ giao dịch (`booking_payments`)
 *
 * Đoàn trả tiền nhiều đợt: cọc khi chốt, phần còn lại trước ngày đi. Hệ thống hiện chỉ có một cột
 * `paid_at` kiểu có/không - mô tả được khách lẻ trả một lần, không mô tả được "đã cọc 30%". Đây
 * chính là lý do điểm 14 phải kéo theo phần lõi của điểm 12 (sổ giao dịch): dựng luồng đoàn trên
 * một cột bật/tắt rồi mới thêm sổ là đập ra làm lại.
 *
 * Sổ ghi từng khoản THU và HOÀN như bút toán: chỉ thêm dòng, không sửa dòng cũ. Số đã thu là tổng
 * của sổ, không phải một cột bị ghi đè - vì tiền là thứ phải đối soát được từng bước.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_booking_requests', function (Blueprint $table) {
            $table->id();

            // Mã tra cứu ngẫu nhiên, cùng cơ chế với đơn lẻ: đại diện đoàn không cần tài khoản.
            $table->uuid('public_token')->unique();

            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->foreignId('tour_schedule_id')->constrained('tour_schedules')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();

            // Người đại diện đứng ra đăng ký cho cả đoàn.
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone', 20);

            /*
             * Ước tính, không phải cam kết. Doanh nghiệp gửi yêu cầu khi còn chưa chốt nội bộ
             * những ai đi; con số thật đưa vào lúc chốt và có thể khác.
             */
            $table->unsignedInteger('estimated_guests');

            // Thông tin xuất hóa đơn giá trị gia tăng - đoàn doanh nghiệp gần như luôn cần.
            // Chỉ lưu đủ để xuất; việc phát hành hóa đơn điện tử ngoài phạm vi (doc 00 §3.2).
            $table->string('company_name')->nullable();
            $table->string('tax_code', 20)->nullable();
            $table->string('invoice_address')->nullable();

            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending_quote');

            /*
             * Báo giá là QUYẾT ĐỊNH CỦA CON NGƯỜI, hệ thống chỉ ghi lại.
             *
             * Không có bảng bậc giá tự động: giảm bao nhiêu cho đoàn 40 người phụ thuộc mùa,
             * quan hệ với khách, chỗ còn trống - đúng loại việc điều hành cân, không phải công
             * thức. Một giá cho mỗi đầu người, kèm số suất miễn phí (thông lệ: trưởng đoàn đi
             * không tính tiền), và hạn hiệu lực để giá không treo vô thời hạn khi chỗ đang bán.
             */
            $table->decimal('quoted_price_per_person', 12, 2)->nullable();
            $table->unsignedInteger('quoted_free_slots')->default(0);
            $table->string('quote_note', 500)->nullable();
            $table->dateTime('quote_expires_at')->nullable();
            $table->dateTime('quoted_at')->nullable();
            $table->foreignId('quoted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('rejected_reason', 500)->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index('tour_schedule_id');
        });

        /*
         * Đơn sinh từ yêu cầu đoàn trỏ ngược về yêu cầu. Một chiều thôi: yêu cầu tìm đơn qua
         * quan hệ hasOne, không thêm cột booking_id bên kia để khỏi có hai nguồn sự thật.
         */
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('group_booking_request_id')
                ->nullable()
                ->after('cancellation_policy_id')
                ->constrained('group_booking_requests')
                ->nullOnDelete();
        });

        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();

            // 'deposit' | 'balance' cộng vào số đã thu, 'refund' trừ ra. Số tiền luôn dương,
            // loại bút toán quyết định dấu - để không bao giờ phải đoán một số âm nghĩa là gì.
            $table->string('kind', 20);
            $table->decimal('amount', 12, 2);

            $table->string('method', 30)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->dateTime('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_booking_request_id');
        });

        Schema::dropIfExists('group_booking_requests');
    }
};
