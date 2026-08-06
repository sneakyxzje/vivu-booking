<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // Điểm tập trung/đón khách chi tiết (địa chỉ + hướng dẫn), hiển thị sau khi đặt tour
            $table->string('pickup_location', 500)->nullable()->after('vehicle_info');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('pickup_location');
        });
    }
};
