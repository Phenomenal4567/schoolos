<?php

namespace App\Exceptions\Fee;

use App\Models\User;

/**
 * Design ref: 22-schoolos-finance-schema.md §2
 *
 * Thrown by FeeAssessmentRepository::assess() when the target user's
 * role does not resolve to 'student' — same shape as
 * InvalidEnrollmentTargetFailure (App\Exceptions\Enrollment), applied to
 * fee_assessments.student_id.
 */
class InvalidFeeAssessmentTargetFailure extends \Exception
{
    public function __construct(public readonly User $targetUser)
    {
        parent::__construct(sprintf(
            "User #%d cannot be fee-assessed as a student: role is '%s', not 'student'.",
            $targetUser->id,
            $targetUser->role->key ?? 'unknown',
        ));
    }
}
