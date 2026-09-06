<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

class ClassSectionControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/admin/class-sections', []);

        $response->assertRedirect('/login');
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id]);

        $response = $this->actingAs($teacher)->post('/admin/class-sections', []);

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_class_section(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $standard = \App\Models\Standard::create(['school_id' => $school->id, 'name' => 'Grade 5']);
        $section = \App\Models\Section::create(['school_id' => $school->id, 'name' => 'A']);

        $response = $this->actingAs($admin)->post('/admin/class-sections', [
            'academic_year_id' => $academicYear->id,
            'standard_id' => $standard->id,
            'section_id' => $section->id,
            'class_teacher_id' => $teacher->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('class_sections', [
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_teacher_id' => $teacher->id,
        ]);
    }

    public function test_creating_with_a_non_teacher_fails_with_a_validation_error(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $notATeacher = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $standard = \App\Models\Standard::create(['school_id' => $school->id, 'name' => 'Grade 5']);
        $section = \App\Models\Section::create(['school_id' => $school->id, 'name' => 'A']);

        $response = $this->actingAs($admin)->post('/admin/class-sections', [
            'academic_year_id' => $academicYear->id,
            'standard_id' => $standard->id,
            'section_id' => $section->id,
            'class_teacher_id' => $notATeacher->id,
        ]);

        $response->assertSessionHasErrors('class_teacher_id');
        $this->assertDatabaseCount('class_sections', 0);
    }

    public function test_a_standard_from_another_school_fails_request_validation(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $standardInOtherSchool = \App\Models\Standard::create(['school_id' => $otherSchool->id, 'name' => 'Grade 5']);
        $section = \App\Models\Section::create(['school_id' => $school->id, 'name' => 'A']);

        $response = $this->actingAs($admin)->post('/admin/class-sections', [
            'academic_year_id' => $academicYear->id,
            'standard_id' => $standardInOtherSchool->id,
            'section_id' => $section->id,
            'class_teacher_id' => $teacher->id,
        ]);

        // Rejected by the Rule::exists()->where('school_id', ...) request
        // validation, before the repository's own cross-tenant guard ever
        // runs.
        $response->assertSessionHasErrors('standard_id');
        $this->assertDatabaseCount('class_sections', 0);
    }

    public function test_admin_can_reassign_the_class_teacher_of_their_own_school(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $originalTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $newTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $originalTeacher);

        $response = $this->actingAs($admin)->post(
            "/admin/class-sections/{$classSection->id}/teacher",
            ['teacher_id' => $newTeacher->id]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('class_sections', [
            'id' => $classSection->id,
            'class_teacher_id' => $newTeacher->id,
        ]);
    }

    public function test_admin_cannot_reassign_a_class_teacher_for_another_schools_class_section(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($otherSchool);
        $teacherInOtherSchool = $this->makeUser([
            'role_id' => $this->makeRole('teacher')->id,
            'school_id' => $otherSchool->id,
        ]);
        $classSectionInOtherSchool = $this->makeClassSection($otherSchool, $academicYear, $teacherInOtherSchool);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $newTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post(
            "/admin/class-sections/{$classSectionInOtherSchool->id}/teacher",
            ['teacher_id' => $newTeacher->id]
        );

        // tenantScope() resolves this the same way a nonexistent id
        // would — a 404, never a 403 that would confirm the id exists.
        $response->assertNotFound();
    }
}
