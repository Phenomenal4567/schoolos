<?php

namespace App\Exceptions\ClassTeacherAssignment;

use App\Models\User;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 16-schoolos-decisions-register.md D3 (same shape of gap,
 * applied to the table D3's own rationale identifies as depending on it)
 *
 * Thrown by ClassTeacherAssignmentRepository::assign() when the target
 * user's role does not resolve to 'teacher'. Nothing at the schema level
 * constrains class_teacher_assignments.teacher_id by role (it's a plain
 * FK to users) — the same gap D3 closed for class_sections.class_teacher_id
 * and InvalidClassTeacherFailure closes there, closed here the same way:
 * one write-path method owns the check, not a trigger or scattered
 * validation. Kept as its own type rather than reusing
 * InvalidClassTeacherFailure, since that class is scoped to the
 * class_sections table specifically (its namespace and docblock both say
 * so) and this is a different table with its own write path.
 */
class InvalidAssignmentTargetFailure extends \Exception
{
    public function __construct(public readonly User $targetUser)
    {
        parent::__construct(sprintf(
            "User #%d cannot be assigned as a class teacher assignment: role is '%s', not 'teacher'.",
            $targetUser->id,
            $targetUser->role->key ?? 'unknown',
        ));
    }
}
