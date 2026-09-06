<?php

namespace App\Events;

use App\Models\SubjectAttendanceRecord;
use App\Models\User;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Subject Attendance" gap)
 *
 * The subject-level analogue of AttendanceCorrected — see that event's
 * doc comment. Fired by SubjectAttendanceRepository::mark() after commit,
 * when the call updated an existing subject_attendance_records row.
 */
class SubjectAttendanceCorrected
{
    public function __construct(
        public readonly SubjectAttendanceRecord $record,
        public readonly User $actor,
        public readonly string $previousStatus,
        public readonly string $newStatus,
        public readonly string $reason,
    ) {
    }
}
