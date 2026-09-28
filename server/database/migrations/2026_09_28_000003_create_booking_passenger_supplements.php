<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_passenger_supplements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_schedule_id')->constrained()->restrictOnDelete();
            $table->uuid('request_key');
            $table->string('payload_hash', 64);
            $table->json('passengers');
            $table->string('reported_by');
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name');
            $table->timestamp('recorded_at');
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sent_by_name')->nullable();
            $table->string('sent_to', 500)->nullable();
            $table->text('sent_note')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_passenger_supplements');
    }
};
