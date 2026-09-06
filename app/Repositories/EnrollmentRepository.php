<?php

namespace App\Repositories;

use App\Exceptions\Enrollment\DuplicateEnrollmentFailure;
use App\Exceptions\Enrollment\InvalidEnrollmentTargetFailure;
use App\Models\ClassSection;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\IdentifierService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 14-schoolos-implementation-plan.md §2
 *
 * Reconstructed from EnrollmentController's doc comments and the
 * cross-tenant-guard shape ClassSectionRepository::create() and
 * ClassTeacherAssignmentRepository::assign() were both explicitly written
 * to expect from this class ("tenant scoping has already happened
 * upstream," "the two closest structural analogs"). Neither of those
 * repositories' doc comments describe this file's exact contents, so this
 * is a best-effort match to the established pattern, not a recovered
 * original — confirm against whatever already lives at this path in the
 * real project before trusting it over that version.
 *
 * The write path that creates a student_enrollments row for a student in
 * a given academic year, per the versioned-by-year shape 05-database-map.md
 * §4 found worth keeping from GegoK12. Deliberately does not audit-log,
 * matching ClassTeacherAssignmentRepository::assign()'s own doc comment on
 * why neither of these two closest structural analogs do.
 *
 * Track 6b addition (20 §4, 16 D12): enroll() is discovery §2.2's "at
 * enrollment" trigger point for IdentifierService::generateStudentId() —
 * the format needs the student's class (the "SS2" in "SCH+ADE+SS2+001"),
 * which only exists once a student_enrollments row does. Generation runs
 * inside the same transaction as the enrollment write and is idempotent
 * (a no-op if the student already has a student_id), so calling enroll()
 * again after a DuplicateEnrollmentFailure never double-generates.
 */
class EnrollmentRepository
{
    public function __construct(private readonly IdentifierService $identifiers)
    {
    }

    /**
     * The one write path for student_enrollments. Verifies the target
     * user's role_id resolves to 'student' before writing, then relies on
     * the schema-level UNIQUE(student_id, academic_year_id) constraint
     * (13 §3) — not a pre-check-then-insert — to reject a second
     * enrollment for the same student/year, closing the same TOCTOU shape
     * 09 §3 found in GegoK12's attendance guard.
     *
     * $schoolId is taken from the caller's own scoped context, never from
     * client input directly, per Ground Rule 0. $classSectionId is
     * checked against it and against $academicYearId — a caller-side
     * consistency bug (e.g. a class section picked from the wrong
     * school's or year's dropdown), not end-user input validation, since
     * tenant scoping has already happened upstream of this call, matching
     * ClassSectionRepository::create()'s guards.
     *
     * @throws InvalidEnrollmentTargetFailure if the target user's role
     *         does not resolve to 'student'. Thrown before the
     *         transaction opens, matching ClassSectionRepository's gate.
     * @throws DuplicateEnrollmentFailure if the student already has an
     *         enrollment row for $academicYearId.
     * @throws \InvalidArgumentException if $classSectionId doesn't belong
     *         to $schoolId, or doesn't belong to $academicYearId.
     */
    public function enroll(
        int $schoolId,
        int $academicYearId,
        int $studentId,
        int $classSectionId,
        string $rollNumber,
        User $actor
    ): StudentEnrollment {
        $student = $this->assertIsStudent($studentId);

        $classSection = ClassSection::findOrFail($classSectionId);

        if ($classSection->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "EnrollmentRepository::enroll(): class_section #{$classSectionId} belongs to "
                . "school #{$classSection->school_id}, not the requested school #{$schoolId}."
            );
        }

        if ($classSection->academic_year_id !== $academicYearId) {
            throw new \InvalidArgumentException(
                "EnrollmentRepository::enroll(): class_section #{$classSectionId} belongs to "
                . "academic year #{$classSection->academic_year_id}, not the requested academic "
                . "year #{$academicYearId}."
            );
        }

        try {
            return DB::transaction(function () use (
                $schoolId,
                $academicYearId,
                $student,
                $classSection,
                $classSectionId,
                $rollNumber
            ) {
                $enrollment = StudentEnrollment::create([
                    'school_id' => $schoolId,
                    'academic_year_id' => $academicYearId,
                    'student_id' => $student->id,
                    'class_section_id' => $classSectionId,
                    'roll_number' => $rollNumber,
                    'status' => 'active',
                ]);

                $this->identifiers->generateStudentId($student, $classSection);

                return $enrollment;
            });
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            // UNIQUE(student_id, academic_year_id) rejected the insert:
            // a genuine double-enrollment attempt, not a race to convert
            // idempotently — unlike ClassTeacherAssignmentRepository::
            // assign(), re-enrolling an already-enrolled student is a
            // business-rule violation the caller needs to see.
            throw new DuplicateEnrollmentFailure($student, $academicYearId);
        }
    }

    /**
     * The one gate this repository requires: verifies $studentId's
     * role_id resolves to 'student', and returns the loaded User so
     * callers don't have to re-fetch it. Mirrors
     * ClassSectionRepository::assertIsTeacher()'s shape exactly.
     *
     * @throws InvalidEnrollmentTargetFailure if the target user's role
     *         does not resolve to 'student'.
     */
    private function assertIsStudent(int $studentId): User
    {
        $targetUser = User::with('role')->findOrFail($studentId);

        if (($targetUser->role->key ?? null) !== 'student') {
            throw new InvalidEnrollmentTargetFailure($targetUser);
        }

        return $targetUser;
    }
}
