<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passenger_checkins', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_passenger_id')
                ->constrained('booking_passengers')
                ->cascadeOnDelete();

            $table->foreignId('itinerary_checkpoint_id')
                ->constrained('itinerary_checkpoints')
                ->cascadeOnDelete();

            $table->string('status');
            $table->text('note')->nullable();

            $table->foreignId('checked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('checked_at')->nullable();

            $table->timestamps();

            $table->unique(
            ['booking_passenger_id', 'itinerary_checkpoint_id'],
            'passenger_checkin_unique'
        );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passenger_checkins');
    }
};