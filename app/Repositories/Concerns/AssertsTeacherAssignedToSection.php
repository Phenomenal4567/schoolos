<?php

namespace App\Repositories\Concerns;

use App\Exceptions\Academic\TeacherNotAssignedToSectionFailure;
use App\Models\ClassSection;
use App\Models\ClassTeacherAssignment;
use App\Models\User;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §5
 *
 * The write-path half of ScopeService::teacherRelationshipScope()'s own
 * $assignedSectionIds computation (class_teacher_assignments rows for
 * this teacher, merged with sections where this teacher is the
 * class_teacher_id homeroom teacher) — same two sources, just phrased as
 * an existence check against one specific section id instead of a pluck
 * across all of them, since a create() call already knows which single
 * section it's authoring against. Shared by TimetableRepository,
 * LessonPlanRepository, AssignmentRepository, and ExamRepository's
 * create() methods (this pass) rather than each repository re-deriving
 * its own copy of the same two-source lookup — the same "one place
 * decides this" discipline ClassSectionRepository::assertIsTeacher()
 * and ClassTeacherAssignmentRepository::assign() already establish for
 * their own single-repository checks, extended here across repositories
 * because all four new create() paths need the literal same check, not
 * four independently-maintained near-copies of it.
 *
 * Deliberately does not itself call tenantScope() or verify $classSectionId
 * belongs to any particular school — a teacher's class_teacher_assignments
 * rows can, by construction, only ever reference sections
 * ClassTeacherAssignmentRepository::assign() placed them on (which only
 * ever writes same-school rows — see that repository's own tests), so
 * this check is tenant-safe by construction the same way
 * ScopeService::teacherRelationshipScope()'s own doc comment describes
 * for its assignment_id/exam_id branches. Each calling repository's
 * create() method still separately verifies $classSectionId's school_id
 * against its own $schoolId parameter as defense-in-depth, matching
 * AttendanceRepository::mark()'s and EnrollmentRepository::enroll()'s
 * identical belt-and-braces guards.
 */
trait AssertsTeacherAssignedToSection
{
    /**
     * @throws TeacherNotAssignedToSectionFailure if $teacher has neither a
     *         class_teacher_assignments row for $classSectionId nor is its
     *         class_teacher_id.
     */
    private function assertTeacherAssignedToSection(User $teacher, int $classSectionId): void
    {
        $isAssigned = ClassTeacherAssignment::where('teacher_id', $teacher->id)
            ->where('class_section_id', $classSectionId)
            ->exists()
            || ClassSection::where('id', $classSectionId)
                ->where('class_teacher_id', $teacher->id)
                ->exists();

        if (! $isAssigned) {
            throw new TeacherNotAssignedToSectionFailure($teacher, $classSectionId);
        }
    }
}
