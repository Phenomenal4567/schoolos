<?php

namespace App\Listeners\Finance;

use App\Events\FeeAssessed;
use App\Models\StudentParentLink;
use App\Models\User;
use App\Notifications\FeeAssessedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Fee / Debt Notifications").
 *
 * Same recipient-resolution shape as
 * Attendance\NotifyParentsOfAbsence::notify() — active
 * StudentParentLink rows for the assessment's student, notified in bulk
 * via Notification::send().
 */
class NotifyParentsOfFeeAssessment
{
    public function handle(FeeAssessed $event): void
    {
        $parentIds = StudentParentLink::where('student_id', $event->assessment->student_id)
            ->where('status', 'active')
            ->pluck('parent_id');

        if ($parentIds->isEmpty()) {
            return;
        }

        $parents = User::whereIn('id', $parentIds)->get();

        Notification::send($parents, new FeeAssessedNotification($event->assessment));
    }
}
