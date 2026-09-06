<?php

namespace App\Exceptions\ClassSection;

use App\Models\User;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 16-schoolos-decisions-register.md D3
 *
 * Thrown by ClassSectionRepository::assignClassTeacher() when the target
 * user's role_id does not resolve to 'teacher'. D3 put the entire
 * `class_teacher_id` invariant behind that one repository method instead
 * of a DB trigger specifically so the rejection is visible in application
 * code, not hidden in schema — this exception is the concrete, catchable
 * form of that rejection. There is no other code path that writes
 * class_teacher_id, so this is the only place a "not a teacher" outcome
 * can originate.
 */
class InvalidClassTeacherFailure extends \Exception
{
    public function __construct(public readonly User $targetUser)
    {
        parent::__construct(sprintf(
            "User #%d cannot be assigned as class teacher: role is '%s', not 'teacher'.",
            $targetUser->id,
            $targetUser->role->key ?? 'unknown',
        ));
    }
}
