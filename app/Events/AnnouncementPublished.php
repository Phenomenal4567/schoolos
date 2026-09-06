<?php

namespace App\Events;

use App\Models\Announcement;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5
 *
 * Fired by AnnouncementRepository::create() after the row is committed —
 * same decoupling shape as AttendanceMarked/AttendanceCorrected (see
 * those classes' doc comments): the repository's job ends at "the
 * announcement was published and the fact was announced," fan-out to
 * recipients is a listener's job, not inline code in the write path.
 * Unlike AttendanceMarked/AttendanceCorrected, this event *does* have a
 * listener from the moment it's introduced (NotifyAudienceOfAnnouncement)
 * — there was no prior phase where announcements existed without
 * anything to notify, the way attendance existed for two phases before
 * Communication.
 */
class AnnouncementPublished
{
    public function __construct(
        public readonly Announcement $announcement,
    ) {
    }
}
