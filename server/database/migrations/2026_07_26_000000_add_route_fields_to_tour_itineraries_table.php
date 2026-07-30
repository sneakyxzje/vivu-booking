<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_itineraries', function (Blueprint $table) {
            $table->string('start_point')->nullable()->after('title');
            $table->string('end_point')->nullable()->after('start_point');
            $table->text('route_points')->nullable()->after('end_point');
            $table->text('rest_stops')->nullable()->after('route_points');
        });
    }

    public function down(): void
    {
        Schema::table('tour_itineraries', function (Blueprint $table) {
            $table->dropColumn([
                'start_point',
                'end_point',
                'route_points',
                'rest_stops',
            ]);
        });
    }
};

