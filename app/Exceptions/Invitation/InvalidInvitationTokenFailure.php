<?php

namespace App\Exceptions\Invitation;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, "New:
 * Invitation mechanism"
 *
 * Thrown by InvitationRepository::accept() when the supplied token's hash
 * matches no row, or matches a row whose status is not 'pending'
 * (already accepted, or revoked). Deliberately one exception for all
 * three cases — "wrong token," "already used," and "revoked" all resolve
 * to the same generic message on the accept page (never confirming to an
 * attacker probing tokens which specific reason applied), matching
 * AuthenticationService's own "user not found" vs. "wrong password"
 * discipline of not leaking which part of a lookup failed where that
 * distinction would help an attacker rather than a legitimate user.
 */
class InvalidInvitationTokenFailure extends \Exception
{
    public function __construct()
    {
        parent::__construct('This invitation link is invalid or has already been used.');
    }
}
