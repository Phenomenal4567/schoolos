<?php

namespace App\Exceptions\Auth;

/**
 * Design ref: 12-schoolos-architecture.md §2
 * Decision ref: 16-schoolos-decisions-register.md D2
 *
 * Base/generic failure for a bad credential against a resolved user —
 * AuthenticationService::authenticate() throws this directly for a wrong
 * password. UserNotFoundFailure and SchoolSuspendedFailure both extend it,
 * so a caller that only wants a single generic "login failed" branch can
 * `catch (AuthFailure $e)`, while a caller that needs to show a specific
 * message (e.g. "your school is suspended" vs. "check your password") can
 * still catch the narrower subclass first.
 *
 * A plain \Exception subclass, never \Error — the direct fix for F16's
 * catch gap (a failure path that previously surfaced as an uncaught
 * \Error, which skipped every catch block written to handle
 * \Exception-typed auth failures and reached the client as a raw 500).
 */
class AuthFailure extends \Exception
{
    public function __construct(string $message = 'The provided credentials are incorrect.')
    {
        parent::__construct($message);
    }
}
