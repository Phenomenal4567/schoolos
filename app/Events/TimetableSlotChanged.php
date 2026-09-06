<?php

namespace App\Events;

use App\Models\TimetableSlot;

/**
 * Design ref: Timetable Management module (School Admin build/publish
 * requirement — teachers should be notified when their timetable
 * changes now that admins, not teachers themselves, own the write
 * path).
 *
 * Fired by TimetableRepository::create()/update()/delete() after the
 * row is committed — same decoupling shape AnnouncementPublished's own
 * doc comment describes: the repository's job ends at "the slot changed
 * and the fact was recorded," notifying the affected teacher is a
 * listener's job. $teacherId is captured directly (not re-read off
 * $slot) so a delete() can still fire this with the teacher who *was*
 * booked, after the row is gone.
 */
class TimetableSlotChanged
{
    public function __construct(
        public readonly ?TimetableSlot $slot,
        public readonly int $teacherId,
        public readonly string $action,
    ) {
    }
}
