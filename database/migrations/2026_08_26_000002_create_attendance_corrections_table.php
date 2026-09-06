<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 13-schoolos-database-schema-v2.md §5, 14-schoolos-implementation-plan.md §3 (Phase 3)
//
// 09-attendance-map.md §4: GegoK12 has no correction capability at all —
// not just a missing audit trail, a missing feature, since its
// class-session-granularity guard made per-student correction
// structurally impossible without redesigning the guard first. Because
// 2026_08_26_000001's unique constraint is per-(student_id, date,
// session) rather than per-class, a correction here is a normal UPDATE
// to attendance_records plus one row here — no guard redesign needed.
//
// Distinct from the generic audit_logs table (2026_08_24_000009):
// audit_logs already has an 'attendance.corrected' example action and
// AttendanceRepository::mark() writes there too for the general activity
// feed, but 13 §5 specifies this dedicated table with typed
// previous_status/new_status/reason columns — audit_logs' before_state/
// after_state JSON blob is not a substitute for a queryable, typed
// corrections history scoped to this one entity type.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained('attendance_records');
            $table->enum('previous_status', ['present', 'absent', 'late', 'excused']);
            $table->enum('new_status', ['present', 'absent', 'late', 'excused']);
            $table->foreignId('corrected_by')->constrained('users');
            $table->timestamp('corrected_at');
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
