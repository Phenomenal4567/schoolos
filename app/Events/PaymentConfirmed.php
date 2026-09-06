<?php

namespace App\Events;

use App\Models\Payment;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Fee / Debt Notifications").
 *
 * Fired the moment a Payment row actually transitions into 'confirmed' —
 * from PaymentRepository::recordManualPayment() (confirmed immediately)
 * and PaymentRepository::confirmOnlinePayment() (only on the branch that
 * just flipped status, never on the idempotent already-confirmed
 * no-op — a webhook retry must not re-notify a parent of a payment they
 * were already told about).
 */
class PaymentConfirmed
{
    public function __construct(
        public readonly Payment $payment,
    ) {
    }
}
