<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bỏ đặt cọc cho đơn lẻ. Khách trả đủ 100% ngay khi đặt.
 *
 * Cọc từng có ở migration 2026_08_30_000003 và bị gỡ trong cùng đợt làm việc — quyết định nghiệp
 * vụ, không phải lỗi kỹ thuật.
 *
 * ## Vì sao là một migration MỚI thay vì sửa lại 000003
 *
 * Sửa một migration đã chạy chỉ an toàn khi chắc chắn chưa máy nào chạy nó. Dự án này phát triển
 * trên một máy rồi kéo về máy khác để thử, nên điều kiện ấy không kiểm chứng được. Thêm rồi bỏ
 * trong hai bước chạy đúng ở cả hai phía: máy đã chạy 000003 thì cột được gỡ, máy chưa chạy thì
 * thêm vào rồi gỡ ngay, và không ai phải sửa cơ sở dữ liệu bằng tay.
 *
 * ## Cái GIỮ LẠI
 *
 * Sổ giao dịch (`booking_payments`) và toàn bộ luồng hoàn tiền không đụng tới. Nhãn `deposit`
 * trong sổ vẫn còn vì **đơn đoàn vẫn đóng cọc** — chỉ khác là mức cọc ấy do điều hành thỏa thuận
 * và ghi tay, không phải một tỷ lệ khai sẵn trên tour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('deposit_percent');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['deposit_amount', 'balance_due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->unsignedTinyInteger('deposit_percent')->nullable()->after('infant_price');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('deposit_amount', 12, 2)->nullable()->after('total_amount');
            $table->dateTime('balance_due_at')->nullable()->after('deposit_amount');
        });
    }
};
