<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L04 - Phân biệt tour ghép và tour riêng.
 *
 * Hai mô hình kinh doanh khác nhau chứ không phải hai thao tác:
 *
 * - Tour ghép: nhiều khách lẻ chung một đoàn, chung xe, chung hướng dẫn viên. Có số khách tối
 *   thiểu, giá thấp hơn. Đây là loại có thể ghép chuyến khi mỗi chuyến ít khách.
 * - Tour riêng: một đoàn đặt trọn chuyến. Không có mức tối thiểu, giá tính theo đoàn.
 *
 * Ghép chuyến chỉ có nghĩa với tour ghép. Dồn hai đoàn riêng vào một chuyến là phá vỡ chính
 * thứ khách đã trả tiền để có: chuyến của riêng họ.
 *
 * Xem docs/nghiep-vu/04-luong-dieu-hanh.md mục 2.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->string('type', 20)->default('shared')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
