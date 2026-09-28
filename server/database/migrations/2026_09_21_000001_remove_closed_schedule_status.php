<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Giữ nguyên hạn chốt và số chỗ: chuyến hết hạn/đầy vẫn không nhận đặt.
        DB::table('tour_schedules')->where('status', 'closed')->update(['status' => 'open']);
    }

    public function down(): void
    {
        // Không thể phân biệt các chuyến vốn open với chuyến đã được quy đổi.
    }
};
