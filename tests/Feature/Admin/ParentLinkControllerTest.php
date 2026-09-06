<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

class ParentLinkControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/admin/parent-links', []);

        $response->assertRedirect('/login');
    }

    public function test_admin_can_link_a_parent_to_a_student(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post('/admin/parent-links', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('student_parent_links', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
    }

    public function test_linking_a_non_parent_fails_with_a_validation_error(): void
    {
        $school = $this->makeSchool();
        $notAParent = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post('/admin/parent-links', [
            'parent_id' => $notAParent->id,
            'student_id' => $student->id,
        ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('student_parent_links', 0);
    }

    public function test_admin_can_unlink_a_link_in_their_own_school(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $link = $this->makeStudentParentLink($school, $parent, $student);

        $response = $this->actingAs($admin)->delete("/admin/parent-links/{$link->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('student_parent_links', [
            'id' => $link->id,
            'status' => 'inactive',
        ]);
    }

    public function test_admin_cannot_unlink_another_schools_link(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $parentInOtherSchool = $this->makeUser([
            'role_id' => $this->makeRole('parent')->id,
            'school_id' => $otherSchool->id,
        ]);
        $studentInOtherSchool = $this->makeUser([
            'role_id' => $this->makeRole('student')->id,
            'school_id' => $otherSchool->id,
        ]);
        $linkInOtherSchool = $this->makeStudentParentLink($otherSchool, $parentInOtherSchool, $studentInOtherSchool);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->delete("/admin/parent-links/{$linkInOtherSchool->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('student_parent_links', [
            'id' => $linkInOtherSchool->id,
            'status' => 'active',
        ]);
    }

    /**
     * Design ref: SchoolOS Account Creation & Onboarding plan, §4 (Parent
     * onboarding)
     */
    public function test_admin_can_approve_a_pending_link_request(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $link = $this->makeStudentParentLink($school, $parent, $student, ['status' => 'pending']);

        $response = $this->actingAs($admin)->post("/admin/parent-links/{$link->id}/approve");

        $response->assertRedirect();
        $this->assertDatabaseHas('student_parent_links', [
            'id' => $link->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_reject_a_pending_link_request(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $link = $this->makeStudentParentLink($school, $parent, $student, ['status' => 'pending']);

        $response = $this->actingAs($admin)->post("/admin/parent-links/{$link->id}/reject");

        $response->assertRedirect();
        $this->assertDatabaseMissing('student_parent_links', ['id' => $link->id]);
    }

    public function test_admin_cannot_approve_another_schools_pending_request(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $parentInOtherSchool = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $otherSchool->id]);
        $studentInOtherSchool = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $otherSchool->id]);
        $linkInOtherSchool = $this->makeStudentParentLink($otherSchool, $parentInOtherSchool, $studentInOtherSchool, ['status' => 'pending']);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post("/admin/parent-links/{$linkInOtherSchool->id}/approve");

        $response->assertNotFound();
        $this->assertDatabaseHas('student_parent_links', [
            'id' => $linkInOtherSchool->id,
            'status' => 'pending',
        ]);
    }

    public function test_approving_an_already_active_link_is_a_no_op_error(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $link = $this->makeStudentParentLink($school, $parent, $student, ['status' => 'active']);

        $response = $this->actingAs($admin)->post("/admin/parent-links/{$link->id}/approve");

        $response->assertSessionHasErrors('parent_link');
        $this->assertDatabaseHas('student_parent_links', ['id' => $link->id, 'status' => 'active']);
    }
}
