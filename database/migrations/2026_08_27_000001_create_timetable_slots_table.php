<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 17-schoolos-academic-domain-map.md §2, §3
// Decision ref: 16-schoolos-decisions-register.md corrections section
// ("Exams/Timetable: core, not an addon/plugin boundary")
//
// Phase 4's first table. GegoK12's own Timetable surface never actually
// worked (15-academic-domain-map.md §1 — Api\TimetableController imports
// a class that has never existed in app/Models, a wrong-namespace bug,
// not a scoping gap), so there is no reference schema to carry forward
// here beyond the addon-boundary decision already made in 16. This is a
// genuinely new SchoolOS table, not a port.
//
// Recurring weekly pattern (day_of_week + period_number), not a
// date-specific slot table — resolves 17 §7's open "timetable slot
// granularity" question. A school's timetable is a template that repeats
// every week for the length of a term/academic year, not a calendar of
// one-off dated events; a date-specific table would need a row per
// class_section per subject per week per year for no benefit this phase
// actually needs, and would make "what's period 3 on Tuesdays" a
// date-range query instead of a direct lookup. If a school ever needs a
// one-off deviation (a holiday substitution, an exam-day override), that
// is a separate concern layered on top of this template, not a reason to
// make every row date-specific.
//
// Carries class_section_id (not student_id) — this is what lets
// ScopeService::relationshipScope()'s existing generic class_section_id
// dispatch cover this table with zero new ScopeService code (17 §3): a
// teacher's assigned sections, a parent/student's own/linked children's
// sections, or (default branch) a school_admin's whole school.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('class_section_id')->constrained('class_sections');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('teacher_id')->constrained('users');
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->unsignedTinyInteger('period_number');
            $table->timestamps();

            // One subject per class_section per day per period — the
            // schema-level guard against double-booking a slot, the same
            // "make the race/mistake structurally impossible" posture
            // attendance_records' and class_teacher_assignments' unique
            // constraints already use in this bundle.
            $table->unique(
                ['class_section_id', 'day_of_week', 'period_number'],
                'timetable_slots_unique_section_day_period'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
