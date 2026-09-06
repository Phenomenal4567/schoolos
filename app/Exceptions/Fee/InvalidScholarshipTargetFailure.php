<?php

namespace App\Exceptions\Fee;

use App\Models\User;

/**
 * Design ref: 22-schoolos-finance-schema.md §3
 *
 * Thrown by ScholarshipRepository::grant() when the target user's role
 * does not resolve to 'student' — same shape as
 * InvalidFeeAssessmentTargetFailure, applied to scholarships.student_id.
 */
class InvalidScholarshipTargetFailure extends \Exception
{
    public function __construct(public readonly User $targetUser)
    {
        parent::__construct(sprintf(
            "User #%d cannot receive a scholarship as a student: role is '%s', not 'student'.",
            $targetUser->id,
            $targetUser->role->key ?? 'unknown',
        ));
    }
}
