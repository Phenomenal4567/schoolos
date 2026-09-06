<?php

namespace Tests\Feature;

use App\Exceptions\Enrollment\DuplicateEnrollmentFailure;
use App\Exceptions\Enrollment\InvalidEnrollmentTargetFailure;
use App\Repositories\EnrollmentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 14-schoolos-implementation-plan.md §2's Phase 2 test gate
 *
 * Mirrors ClassTeacherAssignmentRepositoryTest's shape for the other
 * reconstructed Phase 2 write path.
 */
class EnrollmentRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_enrolling_a_student_creates_the_row(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $enrollment = $this->app->make(EnrollmentRepository::class)->enroll(
            $school->id,
            $academicYear->id,
            $student->id,
            $classSection->id,
            'R1',
            $admin
        );

        $this->assertDatabaseHas('student_enrollments', [
            'id' => $enrollment->id,
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'class_section_id' => $classSection->id,
            'roll_number' => 'R1',
            'status' => 'active',
        ]);
    }

    public function test_enrolling_a_non_student_is_rejected(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $notAStudent = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $this->expectException(InvalidEnrollmentTargetFailure::class);

        $this->app->make(EnrollmentRepository::class)->enroll(
            $school->id,
            $academicYear->id,
            $notAStudent->id,
            $classSection->id,
            'R1',
            $admin
        );
    }

    public function test_enrolling_against_a_class_section_from_another_school_is_rejected(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacherInOtherSchool = $this->makeUser([
            'role_id' => $this->makeRole('teacher')->id,
            'school_id' => $otherSchool->id,
        ]);
        $classSectionInOtherSchool = $this->makeClassSection(
            $otherSchool,
            $this->makeAcademicYear($otherSchool),
            $teacherInOtherSchool
        );
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $this->expectException(\InvalidArgumentException::class);

        $this->app->make(EnrollmentRepository::class)->enroll(
            $school->id,
            $academicYear->id,
            $student->id,
            $classSectionInOtherSchool->id,
            'R1',
            $admin
        );
    }

    public function test_enrolling_the_same_student_twice_in_the_same_year_is_rejected(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $repository = $this->app->make(EnrollmentRepository::class);
        $repository->enroll($school->id, $academicYear->id, $student->id, $classSection->id, 'R1', $admin);

        $this->expectException(DuplicateEnrollmentFailure::class);

        $repository->enroll($school->id, $academicYear->id, $student->id, $classSection->id, 'R2', $admin);

        $this->assertDatabaseCount('student_enrollments', 1);
    }
}
