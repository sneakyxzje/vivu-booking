<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->foreignId('guide_id')->nullable()->after('tour_id')->constrained('users')->nullOnDelete();
        });

        $assignments = DB::table('tour_schedules')
            ->join('tours', 'tours.id', '=', 'tour_schedules.tour_id')
            ->whereNotNull('tours.guide_id')
            ->get(['tour_schedules.id', 'tours.guide_id']);

        foreach ($assignments as $assignment) {
            DB::table('tour_schedules')
                ->where('id', $assignment->id)
                ->update(['guide_id' => $assignment->guide_id]);
        }

        Schema::table('tours', function (Blueprint $table) {
            $table->dropForeign(['guide_id']);
            $table->dropColumn('guide_id');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->foreignId('guide_id')->nullable()->constrained('users')->nullOnDelete();
        });

        $assignments = DB::table('tour_schedules')
            ->whereNotNull('guide_id')
            ->orderBy('id')
            ->get(['tour_id', 'guide_id'])
            ->unique('tour_id');

        foreach ($assignments as $assignment) {
            DB::table('tours')
                ->where('id', $assignment->tour_id)
                ->update(['guide_id' => $assignment->guide_id]);
        }

        Schema::table('tour_schedules', function (Blueprint $table) {
            $table->dropForeign(['guide_id']);
            $table->dropColumn('guide_id');
        });
    }
};