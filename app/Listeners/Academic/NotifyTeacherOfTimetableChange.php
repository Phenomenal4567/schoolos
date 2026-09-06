<?php

namespace App\Listeners\Academic;

use App\Events\TimetableSlotChanged;
use App\Models\User;
use App\Notifications\TimetableSlotChangedNotification;

/**
 * Design ref: Timetable Management module. Registered against
 * TimetableSlotChanged in AppServiceProvider::boot(), matching this
 * bundle's explicit-wiring convention (see AppServiceProvider's own
 * doc comment).
 */
class NotifyTeacherOfTimetableChange
{
    public function handle(TimetableSlotChanged $event): void
    {
        $teacher = User::find($event->teacherId);

        if ($teacher === null) {
            return;
        }

        $teacher->notify(new TimetableSlotChangedNotification($event));
    }
}
