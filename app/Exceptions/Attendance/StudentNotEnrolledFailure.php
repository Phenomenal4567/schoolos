<?php

namespace App\Exceptions\Attendance;

use App\Models\User;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3 (Phase 3 addendum)
 * Decision ref: 16-schoolos-decisions-register.md D3 (same shape of gap,
 * applied to attendance_records.student_id / class_section_id)
 *
 * Thrown by AttendanceRepository::mark() when the target student has no
 * active student_enrollments row for the given class_section_id. Nothing
 * at the schema level ties attendance_records to student_enrollments —
 * both are plain FKs to users/class_sections — so marking attendance for
 * a student who was never enrolled in that class section (or has since
 * transferred/withdrawn) is a caller-side consistency bug, not a race
 * condition, closed here the same way InvalidEnrollmentTargetFailure
 * closes the equivalent gap in EnrollmentRepository.
 */
class StudentNotEnrolledFailure extends \Exception
{
    public function __construct(public readonly User $student, public readonly int $classSectionId)
    {
        parent::__construct(sprintf(
            "User #%d has no active enrollment in class_section #%d — attendance cannot be marked.",
            $student->id,
            $classSectionId,
        ));
    }
}
