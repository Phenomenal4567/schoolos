<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 13-schoolos-database-schema-v2.md §5, 14-schoolos-implementation-plan.md §3 (Phase 3)
//
// Phase 3's first table. Deliberately carries both class_section_id and
// student_id columns — Phase1TestGateTest's row 6/7 doc comments and
// ScopeService::teacherRelationshipScope()/parentRelationshipScope()/
// studentRelationshipScope()'s own doc comments both say this is the shape
// those generic column-presence joins were written to cover once a Phase 3
// table existed. No ScopeService change accompanies this migration — that
// is the point being exercised, not an oversight.
//
// UNIQUE is (student_id, date, session), not (student_id, date) alone — 13
// §5 is explicit that per-session granularity, not per-day, is what makes
// a schema-level constraint the actual structural fix for 09-attendance-
// map.md §3's finding (GegoK12's guard was class-session-granularity and
// check-then-act, catching the wrong thing at the wrong time). Per-day-only
// would have repeated that mistake at a different granularity.
//
// recorded_by/recorded_at match 13 §5's column names exactly (not
// marked_by/timestamps()) — kept consistent with the schema doc since
// AttendanceRepository is this table's only writer and is being built in
// the same pass.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('class_section_id')->constrained('class_sections');
            $table->foreignId('student_id')->constrained('users');
            $table->foreignId('recorded_by')->constrained('users');
            $table->date('date');
            $table->enum('session', ['morning', 'afternoon']);
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
            $table->text('note')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Per-student, per-day, per-session — mark() upserts against
            // this rather than pre-checking, the same TOCTOU-safe pattern
            // ClassTeacherAssignmentRepository::assign() and
            // EnrollmentRepository::enroll() already use. See the
            // migration-level comment above for why session is part of
            // the key, not just date.
            $table->unique(['student_id', 'date', 'session'], 'attendance_records_unique_student_date_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
