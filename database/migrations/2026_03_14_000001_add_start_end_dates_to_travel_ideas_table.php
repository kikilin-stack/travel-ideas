<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_ideas', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('description');
            $table->date('end_date')->nullable()->after('start_date');
        });

        // Backfill existing data so old single-date ideas still show a start date.
        DB::table('travel_ideas')
            ->whereNull('start_date')
            ->whereNotNull('travel_date')
            ->update(['start_date' => DB::raw('travel_date')]);

        DB::table('travel_ideas')
            ->whereNull('end_date')
            ->whereNotNull('start_date')
            ->update(['end_date' => DB::raw('start_date')]);
    }

    public function down(): void
    {
        Schema::table('travel_ideas', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
