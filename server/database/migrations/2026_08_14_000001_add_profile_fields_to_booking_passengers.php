<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G01 - Thông tin hành khách đủ để làm việc với nhà cung cấp.
 *
 * Vì sao cần từng trường, theo docs/nghiep-vu/02-luong-dat-tour.md mục 3.1: mua bảo hiểm du
 * lịch cần đúng ngày sinh và số giấy tờ, xuất vé tàu hoặc vé máy bay sai tên là mất vé, khai
 * báo lưu trú tại khách sạn cần căn cước.
 *
 * Giữ nguyên tên hai cột đã có là date_of_birth và identity_number. Tài liệu 07 gọi chúng là
 * dob và id_number, nhưng đổi tên cột đang chạy chỉ để khớp tài liệu là đánh đổi tệ: phải sửa
 * cả luồng đặt tour, bộ kiểm thử và phía client, đổi lại không được gì.
 *
 * Không thêm passport_expiry. Tài liệu 02 mục 3.1 chỉ đặt luật hạn hộ chiếu cho tour nước
 * ngoài, mà doc 00 xác định phạm vi là công ty lữ hành nội địa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_passengers', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('name');
            $table->string('id_type', 20)->nullable()->after('identity_number');
            $table->string('nationality', 60)->nullable()->after('id_type');
            $table->string('phone', 20)->nullable()->after('nationality');

            // Ăn chay, dị ứng, cần hỗ trợ di chuyển. Khác note ở chỗ đây là thứ hướng dẫn viên
            // và nhà cung cấp phải biết trước, không phải ghi chú nội bộ.
            $table->text('special_request')->nullable()->after('phone');

            // Người liên hệ của đoàn nhỏ trong đơn. Một đơn nhiều người thì hướng dẫn viên cần
            // biết gọi ai, chứ không gọi lần lượt từng người.
            $table->boolean('is_contact')->default(false)->after('special_request');
        });
    }

    public function down(): void
    {
        Schema::table('booking_passengers', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'id_type',
                'nationality',
                'phone',
                'special_request',
                'is_contact',
            ]);
        });
    }
};
