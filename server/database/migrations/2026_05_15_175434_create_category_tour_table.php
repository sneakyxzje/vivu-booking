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
        Schema::create('category_tour', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tour_id')->constrained('tours')->onDelete('cascade');

            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');

            $table->timestamps();

            $table->unique(['tour_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_tour');
    }
};
