<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §5 (Student
 * onboarding)
 */
class StudentControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/admin/students', []);

        $response->assertRedirect('/login');
    }

    public function test_admin_can_register_a_new_student_and_it_is_enrolled(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $this->makeRole('student');

        $response = $this->actingAs($admin)->post('/admin/students', [
            'name' => 'New Student',
            'email' => 'new.student@example.test',
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'roll_number' => 'R1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $student = User::where('email', 'new.student@example.test')->firstOrFail();
        $this->assertSame($school->id, $student->school_id);
        $this->assertSame('student', $student->role->key);
        $this->assertSame('invited', $student->status);
        $this->assertNotNull($student->student_id);

        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id,
            'class_section_id' => $classSection->id,
            'roll_number' => 'R1',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_register_a_student_with_an_immediate_parent_link(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $this->makeRole('student');
        $parent = $this->makeRoleUser('parent', $school);

        $response = $this->actingAs($admin)->post('/admin/students', [
            'name' => 'Linked Student',
            'email' => 'linked.student@example.test',
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'roll_number' => 'R2',
            'parent_ids' => [$parent->id],
        ]);

        $response->assertRedirect();
        $student = User::where('email', 'linked.student@example.test')->firstOrFail();

        $this->assertDatabaseHas('student_parent_links', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
    }

    public function test_a_class_section_from_another_school_is_rejected(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $teacherInOtherSchool = $this->makeRoleUser('teacher', $otherSchool);
        $classSectionInOtherSchool = $this->makeClassSection($otherSchool, $this->makeAcademicYear($otherSchool), $teacherInOtherSchool);
        $this->makeRole('student');

        $response = $this->actingAs($admin)->post('/admin/students', [
            'name' => 'Should Not Register',
            'email' => 'should.not@example.test',
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSectionInOtherSchool->id,
            'roll_number' => 'R1',
        ]);

        $response->assertSessionHasErrors('class_section_id');
        // admin + teacherInOtherSchool only — no student row was created.
        $this->assertDatabaseCount('users', 2);
    }

    public function test_admin_can_invite_a_registered_student_to_enable_login(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $student = $this->makeRoleUser('student', $school, ['status' => 'invited']);

        $response = $this->actingAs($admin)->post("/admin/students/{$student->id}/invite");

        $response->assertRedirect();
        $this->assertDatabaseHas('invitations', [
            'user_id' => $student->id,
            'school_id' => $school->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_cannot_invite_a_student_from_another_school(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $studentInOtherSchool = $this->makeRoleUser('student', $otherSchool, ['status' => 'invited']);

        $response = $this->actingAs($admin)->post("/admin/students/{$studentInOtherSchool->id}/invite");

        $response->assertNotFound();
        $this->assertDatabaseCount('invitations', 0);
    }

    public function test_admin_cannot_invite_a_non_student_user(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $teacher = $this->makeRoleUser('teacher', $school);

        $response = $this->actingAs($admin)->post("/admin/students/{$teacher->id}/invite");

        $response->assertNotFound();
        $this->assertDatabaseCount('invitations', 0);
    }
}
