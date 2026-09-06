<?php

namespace App\Exceptions\Staff;

use App\Models\User;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Rich Teacher/Staff
 * Enrollment And Profiles").
 *
 * Thrown by StaffProfileRepository when $staff's role isn't one of
 * StaffAttendanceRepository::STAFF_ROLE_KEYS (reused directly rather
 * than duplicated — see that repository's own doc comment on why that
 * list is the canonical staff-role set). A distinct class from
 * StaffAttendance\NotAStaffMemberFailure, not a shared one, since that
 * class's message is specifically about attendance eligibility and
 * would misdescribe a profile-write rejection.
 */
class NotAStaffMemberFailure extends \Exception
{
    public function __construct(public readonly User $user)
    {
        $roleKey = $user->role->key ?? 'none';

        parent::__construct(
            "User #{$user->id} (role '{$roleKey}') is not eligible for a staff profile."
        );
    }
}
