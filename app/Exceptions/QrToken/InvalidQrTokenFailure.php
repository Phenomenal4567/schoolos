<?php

namespace App\Exceptions\QrToken;

/**
 * Decision ref: 16-schoolos-decisions-register.md D12.
 *
 * Thrown by QrTokenService::verify() when a token fails to decrypt,
 * is malformed, or resolves to a user who no longer exists or has
 * moved schools since issuance. Deliberately one generic failure for
 * all of those cases — a scanner/attacker gets no signal about *why*
 * a token was rejected (wrong key vs. tampered vs. stale subject),
 * matching standard practice for authentication-token verification
 * failures.
 */
class InvalidQrTokenFailure extends \Exception
{
    public function __construct()
    {
        parent::__construct('QR token is invalid or could not be verified.');
    }
}
