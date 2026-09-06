<?php

namespace App\Repositories;

use App\Models\Role;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §5 (Student
 * onboarding)
 *
 * The direct-registration counterpart to the public admission workflow
 * (Public\AdmissionApplicationController → AdmissionApplicationRepository
 * ::accept()) — for a school that wants to enroll a walk-in student
 * without going through a public application first. register() mirrors
 * accept()'s create-User-then-enroll shape and the same
 * Str::random(32) unusable password, with one deliberate difference:
 * status is set to 'invited', not 'active'. accept() predates the
 * 'invited' enum value (this plan's own addition) and was left
 * unchanged — touching existing, tested admission behavior wasn't worth
 * it for a status label with no functional effect on login either way
 * (AuthenticationService::authenticate() never branches on status; an
 * unusable random password already blocks login regardless of which
 * label the row carries). register() is new code with no such
 * constraint, so it uses the honest label from the start: a freshly
 * registered student has a STUDENT RECORD, and no usable STUDENT LOGIN
 * ACCOUNT until an admin separately calls InvitationRepository::issue()
 * against this same User row (see Admin\StudentController::invite()) —
 * that's what actually flips status to 'active' with a real password.
 *
 * Reuses EnrollmentRepository::enroll() rather than duplicating its
 * class-section/academic-year consistency checks or its
 * IdentifierService::generateStudentId() call — this repository's only
 * new responsibility is creating the User row and, optionally, linking
 * parents; enrollment itself stays EnrollmentRepository's one write path.
 */
class StudentRepository
{
    public function __construct(
        private readonly EnrollmentRepository $enrollments,
        private readonly ParentLinkRepository $parentLinks,
    ) {
    }

    /**
     * @param  list<int>  $parentIds  Existing parent User ids to link
     *         immediately via ParentLinkRepository::link() — the same
     *         admin-initiated write path Admin\ParentLinkController uses,
     *         called here because this whole action is itself
     *         admin/staff-initiated, not a self-service one.
     * @return array{student: User, enrollment: StudentEnrollment}
     */
    public function register(
        int $schoolId,
        int $academicYearId,
        int $classSectionId,
        string $name,
        ?string $email,
        ?string $mobileNo,
        string $rollNumber,
        User $actor,
        array $parentIds = [],
    ): array {
        $studentRoleId = Role::where('key', 'student')->value('id');

        return DB::transaction(function () use (
            $schoolId,
            $academicYearId,
            $classSectionId,
            $name,
            $email,
            $mobileNo,
            $rollNumber,
            $actor,
            $parentIds,
            $studentRoleId,
        ) {
            $student = User::create([
                'school_id' => $schoolId,
                'role_id' => $studentRoleId,
                'name' => $name,
                'email' => $email,
                'mobile_no' => $mobileNo,
                'password' => Str::random(32),
                'status' => 'invited',
            ]);

            $enrollment = $this->enrollments->enroll(
                $schoolId,
                $academicYearId,
                $student->id,
                $classSectionId,
                $rollNumber,
                $actor,
            );

            foreach ($parentIds as $parentId) {
                $this->parentLinks->link($schoolId, (int) $parentId, $student->id, $actor);
            }

            return ['student' => $student->fresh(['role', 'school']), 'enrollment' => $enrollment];
        });
    }
}
