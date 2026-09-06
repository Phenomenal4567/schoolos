<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

class EnrollmentControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/admin/enrollments', []);

        $response->assertRedirect('/login');
    }

    public function test_admin_can_enroll_a_student(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post('/admin/enrollments', [
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'student_id' => $student->id,
            'roll_number' => 'R1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_enrollments', [
            'school_id' => $school->id,
            'student_id' => $student->id,
            'class_section_id' => $classSection->id,
            'roll_number' => 'R1',
        ]);
    }

    public function test_duplicate_enrollment_fails_with_a_validation_error_not_a_500(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $response = $this->actingAs($admin)->post('/admin/enrollments', [
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'student_id' => $student->id,
            'roll_number' => 'R2',
        ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('student_enrollments', 1);
    }

    public function test_a_class_section_from_another_school_fails_request_validation(): void
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

        $response = $this->actingAs($admin)->post('/admin/enrollments', [
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSectionInOtherSchool->id,
            'student_id' => $student->id,
            'roll_number' => 'R1',
        ]);

        $response->assertSessionHasErrors('class_section_id');
        $this->assertDatabaseCount('student_enrollments', 0);
    }
}
