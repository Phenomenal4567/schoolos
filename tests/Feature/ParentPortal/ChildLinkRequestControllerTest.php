<?php

namespace Tests\Feature\ParentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §4 (Parent
 * onboarding)
 */
class ChildLinkRequestControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/parent/child-link-requests', []);

        $response->assertRedirect('/login');
    }

    public function test_non_parent_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $response = $this->actingAs($teacher)->post('/parent/child-link-requests', []);

        $response->assertForbidden();
    }

    public function test_parent_can_request_a_link_by_student_id_and_name(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeRoleUser('parent', $school);
        $student = $this->makeRoleUser('student', $school, [
            'name' => 'Jamie Student',
            'student_id' => 'GHS-2026-001',
        ]);

        $response = $this->actingAs($parent)->post('/parent/child-link-requests', [
            'student_id' => 'GHS-2026-001',
            'name' => 'Jamie Student',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_parent_links', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ]);
    }

    public function test_a_request_never_grants_access_it_only_creates_a_pending_row(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeRoleUser('parent', $school);
        $student = $this->makeRoleUser('student', $school, [
            'name' => 'Jamie Student',
            'student_id' => 'GHS-2026-001',
        ]);

        $this->actingAs($parent)->post('/parent/child-link-requests', [
            'student_id' => 'GHS-2026-001',
            'name' => 'Jamie Student',
        ]);

        $dashboard = $this->actingAs($parent)->get('/parent/dashboard');

        $dashboard->assertOk();
        $dashboard->assertDontSee('View enrollment');

        $showChild = $this->actingAs($parent)->get("/parent/children/{$student->id}");
        $showChild->assertNotFound();
    }

    public function test_a_name_mismatch_is_rejected_with_a_generic_message(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeRoleUser('parent', $school);
        $this->makeRoleUser('student', $school, [
            'name' => 'Jamie Student',
            'student_id' => 'GHS-2026-001',
        ]);

        $response = $this->actingAs($parent)->post('/parent/child-link-requests', [
            'student_id' => 'GHS-2026-001',
            'name' => 'Wrong Name',
        ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('student_parent_links', 0);
    }

    public function test_cannot_request_a_link_to_a_student_in_another_school(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $parent = $this->makeRoleUser('parent', $school);
        $this->makeRoleUser('student', $otherSchool, [
            'name' => 'Jamie Student',
            'student_id' => 'GHS-2026-001',
        ]);

        $response = $this->actingAs($parent)->post('/parent/child-link-requests', [
            'student_id' => 'GHS-2026-001',
            'name' => 'Jamie Student',
        ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('student_parent_links', 0);
    }

    /**
     * Design ref: SchoolOS Account Creation & Onboarding plan, §12
     * (Security Requirements — "user-controlled school_id cannot override
     * the authenticated school context"). This controller only ever
     * reads school scope from $request->user()->school_id — there is no
     * school_id/parent_id input it accepts at all, so a forged one in the
     * body can't reach a query. This test pins that down: a parent in
     * school A cannot use a forged school_id to reach a same-student_id
     * match in school B.
     */
    public function test_a_forged_school_id_field_cannot_reach_another_schools_student(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $parent = $this->makeRoleUser('parent', $school);
        $studentInOtherSchool = $this->makeRoleUser('student', $otherSchool, [
            'name' => 'Jamie Student',
            'student_id' => 'GHS-2026-001',
        ]);

        $response = $this->actingAs($parent)->post('/parent/child-link-requests', [
            'student_id' => 'GHS-2026-001',
            'name' => 'Jamie Student',
            'school_id' => $otherSchool->id,
        ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('student_parent_links', 0);
    }

    public function test_a_second_request_for_the_same_pair_is_a_no_op(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeRoleUser('parent', $school);
        $student = $this->makeRoleUser('student', $school, [
            'name' => 'Jamie Student',
            'student_id' => 'GHS-2026-001',
        ]);

        $this->actingAs($parent)->post('/parent/child-link-requests', [
            'student_id' => 'GHS-2026-001',
            'name' => 'Jamie Student',
        ]);

        $this->actingAs($parent)->post('/parent/child-link-requests', [
            'student_id' => 'GHS-2026-001',
            'name' => 'Jamie Student',
        ]);

        $this->assertDatabaseCount('student_parent_links', 1);
    }
}
