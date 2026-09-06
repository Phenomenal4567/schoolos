<?php

namespace Tests\Feature;

use App\Exceptions\ClassTeacherAssignment\InvalidAssignmentTargetFailure;
use App\Repositories\ClassTeacherAssignmentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 16-schoolos-decisions-register.md D3 (write-path shape);
 * 12 §3a / F18 (this table is what ScopeService::teacherRelationshipScope()
 * depends on)
 *
 * Before ClassTeacherAssignmentRepository existed, nothing in app/ wrote
 * to class_teacher_assignments at all — only the test fixture did, via a
 * bare Eloquent ::create() with no role check. This file is the first
 * exercise of the real write path.
 */
class ClassTeacherAssignmentRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_assigning_a_teacher_creates_the_row(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $assignment = (new ClassTeacherAssignmentRepository())->assign(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $subject->id,
            $teacher->id,
            $admin
        );

        $this->assertDatabaseHas('class_teacher_assignments', [
            'id' => $assignment->id,
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_assigning_a_non_teacher_is_rejected(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $notATeacher = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $this->expectException(InvalidAssignmentTargetFailure::class);

        (new ClassTeacherAssignmentRepository())->assign(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $subject->id,
            $notATeacher->id,
            $admin
        );
    }

    public function test_assigning_against_a_class_section_from_another_school_is_rejected(): void
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
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $this->expectException(\InvalidArgumentException::class);

        (new ClassTeacherAssignmentRepository())->assign(
            $school->id,
            $academicYear->id,
            $classSectionInOtherSchool->id,
            $subject->id,
            $teacher->id,
            $admin
        );
    }

    public function test_assigning_a_subject_from_another_school_is_rejected(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subjectInOtherSchool = $this->makeSubject($otherSchool);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $this->expectException(\InvalidArgumentException::class);

        (new ClassTeacherAssignmentRepository())->assign(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $subjectInOtherSchool->id,
            $teacher->id,
            $admin
        );
    }

    public function test_assigning_the_same_tuple_twice_is_idempotent(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $repository = new ClassTeacherAssignmentRepository();
        $first = $repository->assign(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $subject->id,
            $teacher->id,
            $admin
        );
        $second = $repository->assign(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $subject->id,
            $teacher->id,
            $admin
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('class_teacher_assignments', 1);
    }

    public function test_a_teacher_can_be_assigned_to_a_different_subject_in_the_same_class_section(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $math = $this->makeSubject($school);
        $history = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $repository = new ClassTeacherAssignmentRepository();
        $repository->assign($school->id, $academicYear->id, $classSection->id, $math->id, $teacher->id, $admin);
        $repository->assign($school->id, $academicYear->id, $classSection->id, $history->id, $teacher->id, $admin);

        $this->assertDatabaseCount('class_teacher_assignments', 2);
    }
}
