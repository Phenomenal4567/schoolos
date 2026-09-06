<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 26-discovery-hierarchy-status.md ("Subject Attendance" gap)
//
// "Records the topic taught" is a fact about the class session as a
// whole (one topic per timetable_slot per date), not a per-student fact
// the way status/note on subject_attendance_records is — storing it once
// here rather than duplicating the same string onto every student's
// subject_attendance_records row avoids a multi-row UPDATE (and possible
// inconsistency) every time a teacher edits it, and lets a teacher record
// the topic before, during, or after marking any student, since this row
// isn't gated on any subject_attendance_records row existing yet.
//
// UNIQUE (timetable_slot_id, date): one topic per slot per date.
// SubjectAttendanceRepository::recordTopic() upserts against it.
//
// class_section_id/subject_id are denormalized off the slot, same
// reasoning as subject_attendance_records' own copy of those columns
// (2026_09_05_000001's doc comment) — kept here so a future read surface
// (e.g. a parent/student "recent topics" view) gets ScopeService's
// generic class_section_id dispatch for free, without a join through
// timetable_slots.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_attendance_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('class_section_id')->constrained('class_sections');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('timetable_slot_id')->constrained('timetable_slots');
            $table->date('date');
            $table->text('topic');
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['timetable_slot_id', 'date'], 'subject_attendance_topics_unique_slot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_attendance_topics');
    }
};
