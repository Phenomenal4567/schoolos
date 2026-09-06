<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Notifications\Notification;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Fee / Debt Notifications").
 *
 * database channel only, same reasoning as AttendanceAbsenceNotification.
 */
class PaymentConfirmedNotification extends Notification
{
    public function __construct(
        private readonly Payment $payment,
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
            'type' => 'fee.payment_confirmed',
            'payment_id' => $this->payment->id,
            'fee_assessment_id' => $this->payment->fee_assessment_id,
            'school_id' => $this->payment->school_id,
            'amount' => (string) $this->payment->amount,
            'processor' => $this->payment->processor,
        ];
    }
}
