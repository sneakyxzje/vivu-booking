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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('guest_id')->nullable();
            $table->foreignId('tour_schedule_id')->nullable()->constrained('tour_schedules')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 20)->nullable();
            $table->date('departure_date');
            $table->unsignedInteger('guests');
            $table->decimal('total_amount', 12, 2);
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->text('note')->nullable();
            $table->string('vnpay_transaction_no')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['tour_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['guest_id', 'status']);
            $table->index(['departure_date', 'status']);
            $table->index('vnpay_transaction_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};