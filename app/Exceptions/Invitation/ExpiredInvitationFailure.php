<?php

namespace App\Exceptions\Invitation;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, "New:
 * Invitation mechanism"
 *
 * Thrown by InvitationRepository::accept() when the token hashes to a
 * real, still-'pending' row whose expires_at has already passed. Kept
 * distinct from InvalidInvitationTokenFailure (rather than folding
 * "expired" into the generic invalid-token message) because the accept
 * page's useful next action differs: an expired invitation means "ask
 * whoever invited you to send a new one," not "you mistyped a link."
 */
class ExpiredInvitationFailure extends \Exception
{
    public function __construct()
    {
        parent::__construct('This invitation has expired. Ask whoever invited you to send a new one.');
    }
}
