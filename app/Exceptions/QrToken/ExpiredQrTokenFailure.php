<?php

namespace App\Exceptions\QrToken;

/**
 * Decision ref: 16-schoolos-decisions-register.md D12.
 *
 * Thrown by QrTokenService::verify() specifically for an
 * issueAttendanceToken() token whose embedded expiry has passed.
 * Kept distinct from InvalidQrTokenFailure (rather than folded into
 * it) because the caller-facing UX differs: an expired attendance
 * token means "scan again," not "this code is forged" — the
 * staff-attendance check-in controller can surface that difference,
 * ID-card tokens (no expiry) never raise this.
 */
class ExpiredQrTokenFailure extends \Exception
{
    public function __construct()
    {
        parent::__construct('QR token has expired — please scan again.');
    }
}
