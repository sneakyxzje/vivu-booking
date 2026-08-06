<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Điểm danh từng booking tại từng chặng (ngày lịch trình) của chuyến đi
        Schema::create('booking_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('tour_itinerary_id')->constrained('tour_itineraries')->cascadeOnDelete();
            $table->foreignId('guide_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('present')->default(true);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'tour_itinerary_id']);
        });

        // Ảnh check-in đoàn tại từng chặng của một lịch khởi hành cụ thể
        Schema::create('checkpoint_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_schedule_id')->constrained('tour_schedules')->cascadeOnDelete();
            $table->foreignId('tour_itinerary_id')->constrained('tour_itineraries')->cascadeOnDelete();
            $table->foreignId('guide_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('image_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkpoint_photos');
        Schema::dropIfExists('booking_checkins');
    }
};
