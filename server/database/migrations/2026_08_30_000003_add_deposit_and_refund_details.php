<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đặt cọc, và thông tin để trả tiền hoàn về cho khách.
 *
 * ## Đặt cọc
 *
 * `tours.deposit_percent` — bỏ trống nghĩa là thu đủ 100% như trước. Đặt 30 nghĩa là khách chỉ
 * cần trả 30% để giữ chỗ, phần còn lại trả trước ngày đi.
 *
 * Để trên TOUR chứ không trên toàn hệ thống: tour trong ngày giá 500 nghìn thì thu đủ luôn cho
 * gọn, còn tour 7 ngày giá 15 triệu mà bắt trả hết trong mười phút giữ chỗ thì gần như không ai
 * đặt. Cùng một con số cho cả hai là chọn sai cho một trong hai.
 *
 * `bookings.deposit_amount` chép lại số tiền cọc TẠI THỜI ĐIỂM ĐẶT, cùng lý do với việc chép
 * chính sách hủy vào đơn: sửa tỷ lệ cọc của tour về sau không được đổi nghĩa vụ của đơn đã bán.
 *
 * `bookings.balance_due_at` là hạn trả nốt. Khác `expires_at` — cái kia là mười phút giữ chỗ chờ
 * cổng thanh toán, cái này là hạn hàng tuần trước ngày khởi hành.
 *
 * ## Tài khoản nhận hoàn
 *
 * Không có ba cột này thì hệ thống tính ra "khách được hoàn 2.400.000đ" rồi dừng, và người làm kế
 * toán phải đi gọi từng khách hỏi số tài khoản. Khách khai ngay lúc gửi yêu cầu hủy, là lúc họ
 * đang ngồi trước màn hình và có động lực nhất để khai đúng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->unsignedTinyInteger('deposit_percent')->nullable()->after('infant_price');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('deposit_amount', 12, 2)->nullable()->after('total_amount');
            $table->dateTime('balance_due_at')->nullable()->after('deposit_amount');

            $table->string('refund_bank_account', 50)->nullable()->after('refund_amount');
            $table->string('refund_bank_name', 120)->nullable()->after('refund_bank_account');
            $table->string('refund_account_holder', 120)->nullable()->after('refund_bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('deposit_percent');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_amount',
                'balance_due_at',
                'refund_bank_account',
                'refund_bank_name',
                'refund_account_holder',
            ]);
        });
    }
};
