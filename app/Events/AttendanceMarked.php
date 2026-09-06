<?php

namespace App\Events;

use App\Models\AttendanceRecord;
use App\Models\User;

/**
 * Design ref: 09-attendance-map.md §5, 14-schoolos-implementation-plan.md §3
 *
 * Fired by AttendanceRepository::mark() after the write transaction has
 * committed, when the call created a brand-new attendance_records row
 * (first mark for this student/date/session) — see AttendanceCorrected
 * for the "existing row updated" case, which is a distinct event on
 * purpose rather than one event with a "was this a correction?" flag,
 * so a listener can subscribe to just one without a conditional.
 *
 * This is Phase 3's contract, not Phase 3's delivery mechanism: 09 §5
 * flags GegoK12's inline push/notification dispatch (inside the same
 * loop/transaction as the write) as a coupling worth avoiding, not
 * copying. This event is *only* the decoupling point — no listener, no
 * notification channel, no queue is wired to it yet. That's deliberately
 * left for the Communication phase (14 §5), which owns parent
 * notification preferences/channels and will attach its own listener(s)
 * to this event without any change needed here.
 *
 * Carries the full AttendanceRecord (student_id, school_id,
 * class_section_id, academic_year_id, date, session, status all live on
 * the record itself — see that model) plus $actor, the one piece of
 * "who did this" context the record doesn't already carry as a
 * dedicated relation beyond recorded_by.
 */
class AttendanceMarked
{
    public function __construct(
        public readonly AttendanceRecord $record,
        public readonly User $actor,
    ) {
    }
}