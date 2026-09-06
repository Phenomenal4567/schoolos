<?php

namespace App\Events;

use App\Models\SubjectAttendanceRecord;
use App\Models\User;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Subject Attendance" gap)
 *
 * The subject-level analogue of AttendanceMarked — see that event's doc
 * comment. Fired by SubjectAttendanceRepository::mark() after commit,
 * when the call created a brand-new subject_attendance_records row. No
 * listener is wired to this yet, same deliberate scope note as
 * AttendanceMarked.
 */
class SubjectAttendanceMarked
{
    public function __construct(
        public readonly SubjectAttendanceRecord $record,
        public readonly User $actor,
    ) {
    }
}
