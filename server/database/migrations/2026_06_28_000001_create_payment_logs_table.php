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
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('provider')->default('vnpay');
            $table->string('transaction_no')->nullable();
            $table->string('bank_code')->nullable();
            $table->string('response_code')->nullable();
            $table->string('transaction_status')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->boolean('is_valid_signature')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'provider']);
            $table->index('transaction_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
