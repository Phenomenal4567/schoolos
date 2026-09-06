<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 26-discovery-hierarchy-status.md ("Subject Attendance" gap)
//
// The dedicated, typed correction history for a single
// subject_attendance_records row — the direct analogue of
// attendance_corrections (2026_08_26_000002), for the same reason: the
// generic audit_logs table already gets a 'subject_attendance.corrected'
// entry from SubjectAttendanceRepository::mark(), but its before_state/
// after_state JSON blob is not a substitute for a queryable, typed
// corrections history scoped to this one entity type.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_attendance_record_id')->constrained('subject_attendance_records');
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
        Schema::dropIfExists('subject_attendance_corrections');
    }
};
