<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkpoint_photos', function (Blueprint $table) {
            $table->foreignId('itinerary_checkpoint_id')
                ->nullable()
                ->after('tour_itinerary_id')
                ->constrained('itinerary_checkpoints')
                ->nullOnDelete();

            $table->decimal('latitude', 10, 7)
                ->nullable()
                ->after('image_path');

            $table->decimal('longitude', 10, 7)
                ->nullable()
                ->after('latitude');

            $table->timestamp('captured_at')
                ->nullable()
                ->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('checkpoint_photos', function (Blueprint $table) {
            $table->dropForeign(['itinerary_checkpoint_id']);

            $table->dropColumn([
                'itinerary_checkpoint_id',
                'latitude',
                'longitude',
                'captured_at',
            ]);
        });
    }
};