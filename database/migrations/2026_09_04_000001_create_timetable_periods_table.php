<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Timetable Management module (School Admin requirement: "Configure
// period start/end times"). One row per (school_id, period_number) —
// the school-wide period grid every timetable_slots row's
// period_number is displayed against. Deliberately not
// per-academic_year: a school's daily period structure ("period 1 runs
// 8:00-8:40") rarely changes mid-year and re-scoping it per year would
// force re-entering the same times every time a new academic year is
// created, for no benefit this feature needs — the same reasoning
// timetable_slots' own migration gives for being a recurring weekly
// template rather than a dated one.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->unsignedTinyInteger('period_number');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['school_id', 'period_number'], 'timetable_periods_unique_school_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_periods');
    }
};
