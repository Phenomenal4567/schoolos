<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

class ClassTeacherAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/admin/class-sections/1/teacher-assignments', []);

        $response->assertRedirect('/login');
    }

    public function test_admin_can_assign_a_teacher_to_a_subject(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post(
            "/admin/class-sections/{$classSection->id}/teacher-assignments",
            ['subject_id' => $subject->id, 'teacher_id' => $teacher->id]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('class_teacher_assignments', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $academicYear->id,
        ]);
    }

    public function test_assigning_a_non_teacher_fails_with_a_validation_error(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $notATeacher = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post(
            "/admin/class-sections/{$classSection->id}/teacher-assignments",
            ['subject_id' => $subject->id, 'teacher_id' => $notATeacher->id]
        );

        $response->assertSessionHasErrors('teacher_id');
        $this->assertDatabaseCount('class_teacher_assignments', 0);
    }

    public function test_admin_cannot_assign_against_another_schools_class_section(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($otherSchool);
        $teacherInOtherSchool = $this->makeUser([
            'role_id' => $this->makeRole('teacher')->id,
            'school_id' => $otherSchool->id,
        ]);
        $classSectionInOtherSchool = $this->makeClassSection($otherSchool, $academicYear, $teacherInOtherSchool);
        $subjectInOtherSchool = $this->makeSubject($otherSchool);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post(
            "/admin/class-sections/{$classSectionInOtherSchool->id}/teacher-assignments",
            ['subject_id' => $subjectInOtherSchool->id, 'teacher_id' => $teacherInOtherSchool->id]
        );

        $response->assertNotFound();
    }
}
