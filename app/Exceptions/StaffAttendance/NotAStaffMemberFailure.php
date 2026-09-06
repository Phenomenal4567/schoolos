<?php

namespace App\Exceptions\StaffAttendance;

use App\Models\User;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §7b ("any staff role, not
 * student-specific"). Thrown by StaffAttendanceRepository::checkIn() when
 * $staff's role isn't one of StaffAttendanceRepository::STAFF_ROLE_KEYS —
 * mirrors InvalidEnrollmentTargetFailure's shape for the equivalent gate
 * in EnrollmentRepository.
 */
class NotAStaffMemberFailure extends \Exception
{
    public function __construct(public readonly User $user)
    {
        $roleKey = $user->role->key ?? 'none';

        parent::__construct(
            "User #{$user->id} (role '{$roleKey}') is not eligible for staff attendance."
        );
    }
}
