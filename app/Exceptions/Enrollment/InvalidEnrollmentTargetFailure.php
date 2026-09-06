<?php

namespace App\Exceptions\Enrollment;

use App\Models\User;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 16-schoolos-decisions-register.md D3 (same shape of gap,
 * applied to student_enrollments.student_id)
 *
 * Thrown by EnrollmentRepository::enroll() when the target user's role
 * does not resolve to 'student'. Nothing at the schema level constrains
 * student_enrollments.student_id by role — it's a plain FK to users, the
 * same gap D3 closed for class_sections.class_teacher_id and
 * InvalidClassTeacherFailure closes there. Closed here the same way: one
 * write-path method (assertIsStudent(), private to EnrollmentRepository)
 * owns the check, not a trigger or scattered validation.
 */
class InvalidEnrollmentTargetFailure extends \Exception
{
    public function __construct(public readonly User $targetUser)
    {
        parent::__construct(sprintf(
            "User #%d cannot be enrolled as a student: role is '%s', not 'student'.",
            $targetUser->id,
            $targetUser->role->key ?? 'unknown',
        ));
    }
}
