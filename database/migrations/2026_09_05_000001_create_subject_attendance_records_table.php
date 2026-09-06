<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 26-discovery-hierarchy-status.md ("Subject Attendance" gap)
//
// Closes the discovery-hierarchy gap: class/session attendance
// (attendance_records, 2026_08_26_000001) records whether a student was
// in school for a morning/afternoon session; this table records whether a
// student attended a specific subject period, keyed by timetable_slot_id
// rather than a session enum — the slot already pins class_section_id +
// subject_id + teacher_id for that day/period (see TimetableSlot's own
// doc comment), so a subject-attendance row's "which session" is "which
// slot, which date" instead of attendance_records' "which session enum,
// which date".
//
// Mirrors attendance_records' shape/column names (school_id,
// academic_year_id, class_section_id, student_id, recorded_by, date,
// status, note, recorded_at) so SubjectAttendanceRecord reads exactly
// like AttendanceRecord to anyone who already knows that table — the
// column additions are subject_id and timetable_slot_id (denormalized
// off the slot, same reasoning class_section_id is denormalized onto
// this row rather than requiring a join for ScopeService's generic
// class_section_id/student_id dispatch — see ScopeService's own doc
// comment).
//
// UNIQUE is (student_id, date, timetable_slot_id), the direct analogue of
// attendance_records' (student_id, date, session) constraint: one row per
// student per date per timetable slot. SubjectAttendanceRepository::mark()
// upserts against it with the same lockForUpdate()-then-correct pattern
// AttendanceRepository::mark() uses, not a pre-check.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('class_section_id')->constrained('class_sections');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('timetable_slot_id')->constrained('timetable_slots');
            $table->foreignId('student_id')->constrained('users');
            $table->foreignId('recorded_by')->constrained('users');
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
            $table->text('note')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['student_id', 'date', 'timetable_slot_id'], 'subject_attendance_records_unique_student_date_slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_attendance_records');
    }
};
