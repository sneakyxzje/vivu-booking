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
        Schema::create('booking_change_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users');
            $table->text('reason');
            $table->json('options'); // Mảng các phương án: id, title, description, system_action
            $table->dateTime('response_deadline');

            $table->string('status', 32)->default('pending');
            $table->string('customer_choice')->nullable(); // Lưu option_id
            $table->text('customer_note')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_change_proposals');
    }
};
