<?php

namespace App\Exceptions\Payment;

use App\Models\FeeAssessment;

/**
 * Design ref: 21-schoolos-finance-architecture.md §3
 *
 * Thrown by PaymentRepository when a payment amount is non-positive, or
 * would exceed the fee_assessment's current amount_remaining. Not named
 * in discovery directly, but a direct consequence of amount_remaining
 * being a derived, never-negative figure (21 §2) — allowing an
 * overpayment to be recorded would make that figure meaningless.
 */
class InvalidPaymentAmountFailure extends \Exception
{
    public function __construct(public readonly FeeAssessment $assessment, public readonly string $attemptedAmount)
    {
        parent::__construct(sprintf(
            'Payment amount %s is invalid for fee_assessment #%d (amount_remaining: %s).',
            $attemptedAmount,
            $assessment->id,
            $assessment->amountRemaining(),
        ));
    }
}
