<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->decimal('adult_price', 12, 2)->default(0)->after('discount_price');
            $table->decimal('child_price', 12, 2)->default(0)->after('adult_price');
            $table->decimal('infant_price', 12, 2)->default(0)->after('child_price');
        });

        DB::table('tours')->orderBy('id')->lazyById()->each(function ($tour) {
            $adultPrice = $tour->discount_price ?? $tour->price ?? 0;

            DB::table('tours')
                ->where('id', $tour->id)
                ->update([
                    'adult_price' => $adultPrice,
                    'child_price' => round($adultPrice * 0.7, 2),
                    'infant_price' => 0,
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['adult_price', 'child_price', 'infant_price']);
        });
    }
};