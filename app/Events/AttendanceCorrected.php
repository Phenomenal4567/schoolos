<?php

namespace App\Events;

use App\Models\AttendanceRecord;
use App\Models\User;

/**
 * Design ref: 09-attendance-map.md §5, 14-schoolos-implementation-plan.md §3
 *
 * Fired by AttendanceRepository::mark() after the write transaction has
 * committed, when the call updated an existing attendance_records row
 * (a correction) rather than creating one — see AttendanceMarked's doc
 * comment for why this is a separate event rather than a shared one.
 *
 * $record reflects the post-correction state (record->status is the new
 * status). $previousStatus/$newStatus/$reason are carried separately
 * rather than requiring a listener to go re-query attendance_corrections
 * for the row this event is already about — same rationale
 * AttendanceRepository::mark() itself has for writing both the
 * dedicated attendance_corrections row and the generic audit_logs
 * entry: a listener reacting in real time shouldn't have to do a second
 * read to get context this event already had in hand at dispatch time.
 *
 * Same scope note as AttendanceMarked: no listener/channel/queue is
 * wired to this yet. That belongs to the Communication phase.
 */
class AttendanceCorrected
{
    public function __construct(
        public readonly AttendanceRecord $record,
        public readonly User $actor,
        public readonly string $previousStatus,
        public readonly string $newStatus,
        public readonly string $reason,
    ) {
    }
}