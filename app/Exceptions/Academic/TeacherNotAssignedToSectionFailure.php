<?php

namespace App\Exceptions\Academic;

use App\Models\User;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §5 ("Create/update
 * LessonPlan, Assignment, Exam, Timetable slot | relationshipScope()
 * against the target class_section_id — a teacher can only author
 * content for sections they're assigned to | Generalizes F18's fix to
 * four new resource types")
 *
 * Thrown by TimetableRepository::create(), LessonPlanRepository::create(),
 * AssignmentRepository::create(), and ExamRepository::create() — one
 * shared type across all four, not four separate ones, since it's the
 * exact same check in the exact same shape every time (see
 * App\Repositories\Concerns\AssertsTeacherAssignedToSection, which all
 * four repositories use). Deliberately does not distinguish "no
 * class_teacher_assignments row for this section" from "class_section_id
 * doesn't exist at all" — same "don't hand a caller a way to distinguish
 * those two cases" posture ScopeService's relationship-scoped queries and
 * UnauthorizedLessonPlanReviewFailure both already take; the controllers
 * that catch this convert it to a 404, never a 403, matching
 * AttendanceController's own "a teacher supplying a class_section_id
 * they're not assigned to resolves to 404, identically to 'doesn't
 * exist'" discipline.
 */
class TeacherNotAssignedToSectionFailure extends \Exception
{
    public function __construct(public readonly User $teacher, public readonly int $classSectionId)
    {
        parent::__construct(sprintf(
            "User #%d cannot author content for class_section #%d: no class_teacher_assignments "
            . 'row (and not its class_teacher_id) links this teacher to it.',
            $teacher->id,
            $classSectionId,
        ));
    }
}
