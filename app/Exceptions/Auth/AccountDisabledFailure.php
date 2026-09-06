<?php

namespace App\Exceptions\Auth;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §12 (Security
 * Requirements — "disabled accounts cannot authenticate")
 *
 * Thrown by AuthenticationService::authenticate() when the resolved
 * user's own `status` is 'inactive' or 'exited' (as distinct from
 * AccountNotActivatedFailure's 'invited' case, and from
 * SchoolSuspendedFailure's school-level check). This closes a real gap:
 * before this pass, authenticate() checked school-level suspension but
 * never the user's own status column at all — a user marked inactive/
 * exited who still knew (or was told) their password could log in
 * anyway. Checked before Hash::check(), same reasoning
 * SchoolSuspendedFailure's own doc comment gives: tell a disabled-account
 * holder plainly, not leave them to assume they mistyped a password.
 */
class AccountDisabledFailure extends AuthFailure
{
    public function __construct()
    {
        parent::__construct('This account has been disabled. Contact your school administrator.');
    }
}
