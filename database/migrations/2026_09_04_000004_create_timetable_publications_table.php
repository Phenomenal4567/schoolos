<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Timetable Management module (School Admin requirement: "Preview the
// timetable before publishing" / "Publish/unpublish timetables").
//
// A school's timetable is the *set* of timetable_slots rows for a given
// (school_id, academic_year_id) — there is no single row that "is" the
// timetable to attach a status to. Rather than adding an is_published
// column to every timetable_slots row (which would need updating on
// every single slot every time the whole timetable is published/
// unpublished, and would let two slots in the same school+year
// disagree about publish state, which makes no product sense — a
// school's timetable is published or it isn't, as a whole), this is a
// separate one-row-per-(school_id, academic_year_id) header record
// carrying that single piece of state. Teacher/student/parent portals
// only ever show slots belonging to a published row here (unpublished
// = still a draft only the admin — or a delegated staff member, see
// staff_profiles.can_manage_timetable — can see while building it).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id'], 'timetable_publications_unique_school_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_publications');
    }
};
