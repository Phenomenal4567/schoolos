<?php

namespace App\Exceptions\Enrollment;

use App\Models\User;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 14-schoolos-implementation-plan.md §2's Phase 2 test gate
 * ("one enrollment row per student per academic year enforced at the
 * schema level")
 *
 * Thrown by EnrollmentRepository::enroll() when the schema-level
 * UNIQUE(student_id, academic_year_id) constraint rejects the insert —
 * unlike ClassTeacherAssignmentRepository::assign(), a second enroll()
 * call for the same student/year is a genuine business-rule violation
 * (a student enrolling twice), not an idempotent re-statement of an
 * already-true fact, so this surfaces as a typed rejection rather than
 * returning the existing row.
 */
class DuplicateEnrollmentFailure extends \Exception
{
    public function __construct(public readonly User $student, public readonly int $academicYearId)
    {
        parent::__construct(sprintf(
            "Student #%d is already enrolled for academic year #%d.",
            $student->id,
            $academicYearId,
        ));
    }
}
