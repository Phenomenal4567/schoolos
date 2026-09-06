<?php

namespace App\Exceptions\StaffAttendance;

use App\Models\User;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §7b
 * (UNIQUE(user_id, date)).
 *
 * Thrown by StaffAttendanceRepository::checkIn() when the schema-level
 * unique constraint rejects the insert — same TOCTOU-safe shape as
 * DuplicateEnrollmentFailure (schema constraint checked via a caught
 * QueryException, not a pre-check-then-insert).
 */
class DuplicateStaffAttendanceFailure extends \Exception
{
    public function __construct(public readonly User $user, public readonly string $date)
    {
        parent::__construct(
            "Staff attendance for user #{$user->id} on {$date} has already been recorded."
        );
    }
}
