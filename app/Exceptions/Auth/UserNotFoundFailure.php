<?php

namespace App\Exceptions\Auth;

/**
 * Design ref: 12-schoolos-architecture.md §2
 * Decision ref: 16-schoolos-decisions-register.md D2 (typed-failure pattern
 * carried into every auth outcome, not just suspension)
 *
 * Thrown by AuthenticationService::resolveIdentifier() when the raw login
 * input matches none of the three unique identifier columns
 * (email / mobile_no / registration_number), and by
 * AuthenticationService::authenticate() when a successfully-resolved
 * identifier still matches no user row.
 *
 * F5's root cause was a downstream check that assumed a user had already
 * been found and dereferenced a null; F16's was that the resulting failure
 * surfaced as an uncaught \Error rather than \Exception, so it skipped
 * every catch block the caller had written for auth failures. Being a
 * typed subclass of AuthFailure closes both at once: "not found" is
 * always an object the caller can inspect, never a null the caller has to
 * remember to check for, and it is always a catchable \Exception.
 */
class UserNotFoundFailure extends AuthFailure
{
    public function __construct(string $message = 'No account matches the provided identifier.')
    {
        parent::__construct($message);
    }
}
