<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O - Sự cố dọc đường và chi phí phát sinh.
 *
 * Câu hỏi của hội đồng rất cụ thể: đi tàu ra biển gặp bão, phải đổi sang chương trình khác đắt
 * hơn, xử lý thế nào. Trước đây hệ thống không có chỗ nào ghi nhận việc đó - đoàn gặp chuyện thì
 * mọi thứ diễn ra ngoài phần mềm, và tới lúc khách khiếu nại thì không còn gì để đối chiếu.
 *
 * Tách làm hai bảng vì đó là hai câu hỏi khác nhau:
 *
 *   - `schedule_incidents` trả lời "chuyện gì đã xảy ra với cả đoàn"
 *   - `booking_surcharges` trả lời "ai phải trả thêm bao nhiêu, hoặc được hoàn bao nhiêu"
 *
 * Gộp một bảng thì không mô tả được tình huống thường gặp nhất: một sự cố nhưng mỗi đơn chịu một
 * khoản khác nhau, có đơn không chịu gì.
 *
 * Xem docs/nghiep-vu/04-luong-dieu-hanh.md mục 6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_incidents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tour_schedule_id')->constrained('tour_schedules')->cascadeOnDelete();

            // Xảy ra ở chặng nào. Để trống được: bão giữa biển không thuộc chặng nào cụ thể.
            $table->foreignId('tour_itinerary_id')->nullable()
                ->constrained('tour_itineraries')->nullOnDelete();

            $table->string('type', 20);
            $table->string('severity', 10);
            $table->string('status', 20)->default('reported');

            /*
             * Thời điểm xảy ra, do người báo nhập, tách khỏi created_at.
             *
             * Giữa biển hoặc trên núi thì mất sóng là chuyện thường, báo cáo về tới hệ thống có
             * khi muộn nhiều giờ. Lấy created_at làm thời điểm sự cố sẽ ghi sai lúc nó thật sự
             * xảy ra, mà đó lại là con số dùng để đối chiếu khi có khiếu nại.
             */
            $table->dateTime('occurred_at');
            $table->boolean('reported_late')->default(false);

            $table->text('description');

            // Hướng dẫn viên báo cáo. Người này KHÔNG được quyết chi phí.
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();

            // Phần chỉ điều hành được ghi.
            $table->text('resolution')->nullable();
            $table->decimal('cost_delta', 12, 2)->nullable();
            $table->string('who_bears', 20)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['tour_schedule_id', 'occurred_at']);
            $table->index(['status', 'severity']);
        });

        Schema::create('incident_photos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('schedule_incident_id')
                ->constrained('schedule_incidents')->cascadeOnDelete();

            $table->string('image_path');
            $table->string('caption', 255)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('schedule_incident_id');
        });

        Schema::create('booking_surcharges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('schedule_incident_id')
                ->constrained('schedule_incidents')->cascadeOnDelete();

            // surcharge: khách trả thêm. refund: hoàn cho khách phần chương trình không dùng được.
            // Một sự cố có thể sinh cả hai loại cho cùng một đơn.
            $table->string('kind', 20);

            $table->decimal('amount', 12, 2);
            $table->text('reason');

            $table->string('status', 20)->default('pending');

            // Chỉ điều hành duyệt. Chưa duyệt thì khoản này chưa có hiệu lực với khách.
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();

            /*
             * Khách đã đồng ý chưa, và ai ghi nhận việc đồng ý đó.
             *
             * Khoản khách phải chịu mà không có xác nhận thì tới lúc tranh chấp không bảo vệ được
             * bên nào. Ghi thời điểm và ghi chú tại chỗ, kèm ảnh biên bản ở incident_photos.
             */
            $table->dateTime('customer_consent_at')->nullable();
            $table->text('consent_note')->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index('schedule_incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_surcharges');
        Schema::dropIfExists('incident_photos');
        Schema::dropIfExists('schedule_incidents');
    }
};
