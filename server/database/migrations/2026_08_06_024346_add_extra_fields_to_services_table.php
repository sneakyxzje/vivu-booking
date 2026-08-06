<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Mô tả ngắn cho dịch vụ (khách sạn, ăn uống, ...)
            $table->text('description')->nullable()->after('icon');

            // Giá dịch vụ phát sinh (nếu có, nullable = bao gồm trong giá tour)
            $table->decimal('price', 12, 2)->nullable()->after('description');

            // Cho phép admin bật/tắt dịch vụ này
            $table->boolean('is_active')->default(true)->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['description', 'price', 'is_active']);
        });
    }
};
