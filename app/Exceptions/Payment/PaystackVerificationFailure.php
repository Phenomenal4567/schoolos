<?php

namespace App\Exceptions\Payment;

/**
 * Design ref: 21-schoolos-finance-architecture.md §3, D13
 *
 * Thrown by PaymentRepository::confirmOnlinePayment() when Paystack's
 * own API does not confirm a transaction reference as successful — the
 * direct enforcement of "never trust a client-supplied success callback
 * alone" (21 §3), the same trust-boundary discipline 12 §2 applies to
 * authentication.
 */
class PaystackVerificationFailure extends \Exception
{
    public function __construct(public readonly string $paystackReference, string $detail)
    {
        parent::__construct(sprintf(
            "Paystack could not verify transaction reference '%s': %s",
            $paystackReference,
            $detail,
        ));
    }
}
