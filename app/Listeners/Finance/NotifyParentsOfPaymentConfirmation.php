<?php

namespace App\Listeners\Finance;

use App\Events\PaymentConfirmed;
use App\Models\StudentParentLink;
use App\Models\User;
use App\Notifications\PaymentConfirmedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Fee / Debt Notifications").
 *
 * Same recipient-resolution shape as
 * Attendance\NotifyParentsOfAbsence::notify(). Payment carries no
 * student_id of its own (see that model's own doc comment), so the
 * student is resolved through its owning fee_assessment.
 */
class NotifyParentsOfPaymentConfirmation
{
    public function handle(PaymentConfirmed $event): void
    {
        $studentId = $event->payment->feeAssessment->student_id;

        $parentIds = StudentParentLink::where('student_id', $studentId)
            ->where('status', 'active')
            ->pluck('parent_id');

        if ($parentIds->isEmpty()) {
            return;
        }

        $parents = User::whereIn('id', $parentIds)->get();

        Notification::send($parents, new PaymentConfirmedNotification($event->payment));
    }
}
