<?php

namespace App\Contracts;

/**
 * Design ref: 21-schoolos-finance-architecture.md §3, D13.
 *
 * A thin contract, not a processor-abstraction layer for its own sake —
 * D13 confirmed Paystack directly, no multi-processor interface was
 * asked for. This interface exists so PaymentRepository never talks to
 * an HTTP client directly (untestable, and a trust-boundary violation
 * waiting to happen if a future call site swaps in something that
 * doesn't verify) — PaystackHttpClient is the real implementation, bound
 * in AppServiceProvider; tests bind a fake.
 */
interface PaystackClient
{
    /**
     * Verifies a transaction reference against Paystack's own API.
     * Returns true only if Paystack confirms the transaction as
     * successful — never based on anything the caller supplied about
     * the transaction's outcome.
     */
    public function verifyTransaction(string $reference): bool;
}
