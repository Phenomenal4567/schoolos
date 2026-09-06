<?php

namespace App\Exceptions\Auth;

/**
 * Design ref: SchoolOS Onboarding & Authentication UI — demo/trial
 * billing addition.
 *
 * Thrown by AuthenticationService::authenticate() when the resolved
 * user's school is on a 'trial' plan whose trial_ends_at has passed
 * (School::isTrialExpired()). Its own type, not a fold-in to
 * SchoolSuspendedFailure, for the same reason SchoolSuspendedFailure is
 * its own type rather than a generic AuthFailure: the two are different
 * facts a person needs to act on differently (contact support about a
 * suspension vs. upgrade/renew a trial), even though both currently
 * block login the same way.
 */
class TrialExpiredFailure extends AuthFailure
{
    public function __construct()
    {
        parent::__construct('Your school\'s free trial has ended. Contact SchoolOS to continue.');
    }
}
