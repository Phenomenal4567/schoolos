<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Timetable Management module (School Admin requirement: "Select the
// school days"). JSON list of day_of_week keys
// (['monday','tuesday',...]) matching timetable_slots.day_of_week's own
// enum values exactly, so the two never drift into two different day
// vocabularies. Null means "not configured yet" — TimetableRepository
// falls back to Monday-Friday in that case rather than forcing every
// existing/seeded school through a one-time settings step before its
// timetable becomes usable.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->json('working_days')->nullable()->after('school_type');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('working_days');
        });
    }
};
