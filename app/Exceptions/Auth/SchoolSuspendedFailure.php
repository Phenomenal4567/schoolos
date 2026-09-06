<?php

namespace App\Exceptions\Auth;

/**
 * Decision ref: 16-schoolos-decisions-register.md D2
 *
 * Thrown by AuthenticationService::authenticate() when the resolved user's
 * school has `status = 'suspended'`. Deliberately its own type rather than
 * reusing AuthFailure's generic message, per D2: "All login attempts for
 * users of that school fail with a typed SchoolSuspended auth failure (not
 * a generic invalid-credentials message)" — a user at a suspended school
 * should be told that plainly, not left to assume they mistyped a
 * password. This check runs on every login attempt, not only at the
 * moment of suspension, so callers can rely on it staying accurate as a
 * school's status changes over time.
 *
 * Extends AuthFailure rather than UserNotFoundFailure: the user *was*
 * resolved successfully here — it's the school, not the identifier, that
 * failed the check — so a caller catching AuthFailure broadly still sees
 * this as an auth failure, while one that cares about the distinction can
 * catch SchoolSuspendedFailure specifically.
 *
 * $schoolId is optional (defaults to null) so existing call sites that
 * construct this with no arguments, per AuthenticationService's sketch,
 * keep working; callers that have the id on hand can pass it through for
 * a more specific message to the end user.
 */
class SchoolSuspendedFailure extends AuthFailure
{
    public function __construct(public readonly ?int $schoolId = null)
    {
        parent::__construct('This school\'s account is currently suspended.');
    }
}
