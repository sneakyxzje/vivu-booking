<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itinerary_checkpoints', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tour_itinerary_id')
                ->constrained('tour_itineraries')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->unsignedInteger('sequence')->default(1);

            $table->boolean('is_required_photo')->default(false);

            $table->timestamps();

            $table->index(['tour_itinerary_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itinerary_checkpoints');
    }
};