<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Timetable Management module. SchoolOS has no dedicated Room/Classroom
// entity yet (no rooms table, no Room model) — this is deliberately a
// free-text label, not a foreign key, so it does not invent a parallel
// "room management" system the product doesn't have. It exists so a
// slot can carry a room label for display and so
// TimetableRepository can apply the room-conflict check the task's own
// spec makes conditional ("if room management exists") in the one weak
// sense that's actually available today: two slots naming the same
// literal room text can't share a day+period. Nullable — most schools
// have no rooms to track and shouldn't be forced to fill this in.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->string('room')->nullable()->after('period_number');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropColumn('room');
        });
    }
};
