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
    Schema::create('booking_passengers', function (Blueprint $table) {
        $table->id();

        $table->foreignId('booking_id')
              ->constrained()
              ->cascadeOnDelete();

        $table->string('full_name');
        $table->date('birth_date');
        $table->enum('gender', ['male', 'female']);
        $table->string('phone')->nullable();
        $table->string('email')->nullable();
        $table->string('identity_number')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_passengers');
    }
};
