<?php

namespace App\Exceptions\Academic;

use App\Models\User;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §5 (F29)
 *
 * Thrown by AssignmentSubmissionRepository::grade() and
 * ExamMarkRepository::record() when the acting teacher's row doesn't
 * resolve through ScopeService::tenantScope()->relationshipScope() for
 * AssignmentSubmission/ExamMark — either it belongs to another school, or
 * it belongs to a class_section the teacher has no class_teacher_assignments
 * row for (the exact F29 regression: a teacher assigned to a *different*
 * section must not be able to grade it). One exception type for both
 * causes, same "don't let a caller distinguish wrong-tenant from
 * wrong-section from doesn't-exist" posture as
 * UnauthorizedLessonPlanReviewFailure — callers convert this to a 404,
 * never a 403, matching every other teacher-scope violation in this
 * bundle (TeacherNotAssignedToSectionFailure's own doc comment).
 */
class UnauthorizedGradingFailure extends \Exception
{
    public function __construct(public readonly User $actor, public readonly int $targetId)
    {
        parent::__construct(sprintf(
            "User #%d cannot grade/mark record #%d: it does not resolve within this user's "
            . 'tenant and section scope.',
            $actor->id,
            $targetId,
        ));
    }
}
