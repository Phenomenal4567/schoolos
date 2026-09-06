<?php

namespace App\Exceptions\Auth;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, "New:
 * Invitation mechanism"
 *
 * Thrown by AuthenticationService::authenticate() when the resolved
 * user's status is 'invited' — an account InvitationRepository::issue()
 * created, with no usable password yet, that InvitationRepository::
 * accept() hasn't been completed for. Functionally this login attempt
 * would already fail Hash::check() against the unusable random password
 * every invited account carries, but surfacing that as a generic wrong-
 * password message would be actively misleading — there is no password
 * to have gotten right. This tells the person what's actually true: open
 * the invitation, don't retry a password.
 */
class AccountNotActivatedFailure extends AuthFailure
{
    public function __construct()
    {
        parent::__construct('This account is not active yet. Check your email for an invitation link.');
    }
}
