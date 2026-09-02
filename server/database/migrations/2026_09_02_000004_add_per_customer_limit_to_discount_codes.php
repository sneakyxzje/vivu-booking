<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giới hạn số lần MỘT khách được dùng một mã giảm giá.
 *
 * `discount_codes` mới chỉ có `usage_limit` — tổng số lượt của cả mã. Một mã "giảm 500k cho khách
 * mới" phát 100 lượt có thể bị đúng một người dùng cả 100 lần: không có gì đếm theo người.
 *
 * Để trống nghĩa là không giới hạn theo người, giữ nguyên hành vi cũ cho các mã đang chạy — mã
 * khuyến mãi toàn quốc kiểu "giảm 5% dịp lễ" đúng là không cần giới hạn ấy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->unsignedInteger('per_customer_limit')->nullable()->after('usage_limit');
        });
    }

    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->dropColumn('per_customer_limit');
        });
    }
};
