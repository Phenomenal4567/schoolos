<?php

namespace App\Listeners\Attendance;

use App\Events\AttendanceCorrected;
use App\Events\AttendanceMarked;
use App\Models\StudentParentLink;
use App\Models\User;
use App\Notifications\AttendanceAbsenceNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Design ref: 09-attendance-map.md §5, 14-schoolos-implementation-plan.md §3, §5
 * Decision ref: 16-schoolos-decisions-register.md D8
 *
 * Closes the specific gap AttendanceMarked's own doc comment named: "no
 * listener, no notification channel, no queue is wired to it yet ...
 * deliberately left for the Communication phase." handle() only reacts on
 * $event->record->status === 'absent' — a present/late/half_day mark
 * (whatever this schema's other status values are) doesn't warrant a
 * parent notification, only the specific event GegoK12's original
 * fan-out was built for.
 *
 * handleCorrection() (D8, resolved): a correction that changes status
 * *to* 'absent' fires the same notification a first-time absent mark
 * would. It's a second method on this listener rather than a new class
 * because the recipient resolution and notification payload are
 * identical to handle()'s — the only difference is which event supplies
 * the record, and AttendanceCorrected's own record already reflects the
 * post-correction state (see that event's doc comment), so
 * $event->record can be passed straight through. Only fires when the
 * correction's *new* status is 'absent' and the *previous* status wasn't
 * already 'absent' — an already-absent row corrected for an unrelated
 * reason (e.g. fixing a note) must not re-notify parents of the same
 * fact a second time.
 */
class NotifyParentsOfAbsence
{
    public function handle(AttendanceMarked $event): void
    {
        $this->notify($event->record);
    }

    public function handleCorrection(AttendanceCorrected $event): void
    {
        if ($event->newStatus !== 'absent' || $event->previousStatus === 'absent') {
            return;
        }

        $this->notify($event->record);
    }

    private function notify(\App\Models\AttendanceRecord $record): void
    {
        if ($record->status !== 'absent') {
            return;
        }

        $parentIds = StudentParentLink::where('student_id', $record->student_id)
            ->where('status', 'active')
            ->pluck('parent_id');

        if ($parentIds->isEmpty()) {
            return;
        }

        $parents = User::whereIn('id', $parentIds)->get();

        Notification::send($parents, new AttendanceAbsenceNotification($record));
    }
}
