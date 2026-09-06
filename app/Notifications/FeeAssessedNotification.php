<?php

namespace App\Notifications;

use App\Models\FeeAssessment;
use Illuminate\Notifications\Notification;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Fee / Debt Notifications").
 *
 * database channel only, same reasoning as AttendanceAbsenceNotification.
 */
class FeeAssessedNotification extends Notification
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
            'type' => 'fee.assessed',
            'fee_assessment_id' => $this->assessment->id,
            'school_id' => $this->assessment->school_id,
            'student_id' => $this->assessment->student_id,
            'fee_category_id' => $this->assessment->fee_category_id,
            'amount_due' => (string) $this->assessment->amount_due,
            'due_date' => $this->assessment->due_date?->toDateString(),
        ];
    }
}
