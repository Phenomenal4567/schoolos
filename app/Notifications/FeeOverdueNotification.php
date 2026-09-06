<?php

namespace App\Notifications;

use App\Models\FeeAssessment;
use Illuminate\Notifications\Notification;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Fee / Debt Notifications").
 *
 * Sent by SendFeeReminders (app/Console/Commands/SendFeeReminders.php)
 * for an assessment whose due_date has passed and still carries a
 * balance. database channel only, same reasoning as
 * AttendanceAbsenceNotification.
 */
class FeeOverdueNotification extends Notification
{
    public function __construct(
        private readonly FeeAssessment $assessment,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'fee.overdue',
            'fee_assessment_id' => $this->assessment->id,
            'school_id' => $this->assessment->school_id,
            'student_id' => $this->assessment->student_id,
            'fee_category_id' => $this->assessment->fee_category_id,
            'amount_remaining' => $this->assessment->amountRemaining(),
            'due_date' => $this->assessment->due_date?->toDateString(),
        ];
    }
}
