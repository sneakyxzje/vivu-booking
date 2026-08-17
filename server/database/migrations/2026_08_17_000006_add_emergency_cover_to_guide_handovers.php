<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đánh dấu lần bàn giao là nhờ hướng dẫn viên của đoàn khác trông hộ.
 *
 * Tình huống: đoàn đang trên đường, chỉ có một hướng dẫn viên, người đó ốm giữa chừng. Người thay
 * hợp lý nhất không phải ai đang ở nhà - họ cách đoàn nhiều giờ đường - mà là hướng dẫn viên đang
 * dẫn một đoàn khác cùng lúc, ở gần đó. Hai người tự thu xếp với nhau.
 *
 * Nhưng như vậy người nhận đang giữ **hai đoàn cùng lúc**, tức phá đúng cái luật hệ thống vẫn
 * chặn ở mọi chỗ khác. Cho phép ở đây là quyết định có cân nhắc: một người trông hai đoàn thì tệ,
 * nhưng **vẫn hơn một đoàn không có ai**. Đây là biện pháp chữa cháy, không phải cách vận hành
 * bình thường.
 *
 * Vì thế phải đánh dấu. Không có cột này thì trong dữ liệu nó trông y hệt một lần phân công bình
 * thường, và không ai biết là còn việc dở phải xử lý tiếp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_handovers', function (Blueprint $table) {
            $table->boolean('is_emergency_cover')->default(false)->after('handover_note');
        });
    }

    public function down(): void
    {
        Schema::table('guide_handovers', function (Blueprint $table) {
            $table->dropColumn('is_emergency_cover');
        });
    }
};
