<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 14-schoolos-implementation-plan.md §2 (parent web dashboard)
 *
 * Exercises the same F20/F18 scoping guarantees ScopeServiceTest and
 * Phase1TestGateTest already cover at the service layer, but through the
 * actual HTTP route this time — per 14 §2's test gate: "Phase 1's parent/
 * child scope tests re-run against real populated data shapes." This is
 * the first place those guarantees are checked end-to-end through
 * middleware + controller + view, not just ScopeService in isolation.
 */
class ParentDashboardTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/parent/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_non_parent_role_is_forbidden(): void
    {
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id]);

        $response = $this->actingAs($teacher)->get('/parent/dashboard');

        $response->assertForbidden();
    }

    public function test_parent_sees_only_own_linked_children(): void
    {
        $school = $this->makeSchool();
        $studentRole = $this->makeRole('student');

        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $ownChild = $this->makeUser(['role_id' => $studentRole->id, 'school_id' => $school->id, 'name' => 'Own Child']);
        $otherChild = $this->makeUser(['role_id' => $studentRole->id, 'school_id' => $school->id, 'name' => 'Other Child']);

        $this->makeStudentParentLink($school, $parent, $ownChild);
        // otherChild deliberately has no link to this parent.

        $response = $this->actingAs($parent)->get('/parent/dashboard');

        $response->assertOk();
        $response->assertSee('Own Child');
        $response->assertDontSee('Other Child');
    }

    /**
     * Direct F20 regression through the HTTP layer: manipulating the
     * {student} route parameter to another parent's child must not
     * reveal that child's record, and must fail identically to a
     * nonexistent id (404), not a 403 that would confirm the id is valid.
     */
    public function test_parent_cannot_view_another_parents_child(): void
    {
        $school = $this->makeSchool();
        $studentRole = $this->makeRole('student');
        $parentRole = $this->makeRole('parent');

        $parentA = $this->makeUser(['role_id' => $parentRole->id, 'school_id' => $school->id, 'email' => 'parent-a@example.test']);
        $parentB = $this->makeUser(['role_id' => $parentRole->id, 'school_id' => $school->id, 'email' => 'parent-b@example.test']);
        $childOfB = $this->makeUser(['role_id' => $studentRole->id, 'school_id' => $school->id]);

        $this->makeStudentParentLink($school, $parentB, $childOfB);

        $response = $this->actingAs($parentA)->get("/parent/children/{$childOfB->id}");

        $response->assertNotFound();
    }

    public function test_inactive_link_hides_the_child(): void
    {
        $school = $this->makeSchool();
        $studentRole = $this->makeRole('student');
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $child = $this->makeUser(['role_id' => $studentRole->id, 'school_id' => $school->id]);

        // student_parent_links.status is enum('active', 'inactive') — see
        // 2026_08_24_000007_create_enrollments_parent_links_tables.php.
        // 'inactive' is the real value; an earlier draft of this test used
        // 'revoked', which isn't a valid enum member. SQLite doesn't
        // enforce Laravel's enum() as a real CHECK/constraint, so that
        // version would have passed here while silently testing nothing
        // meaningful, and would have thrown against MySQL. Caught on
        // review before either happened, since ScopeService's own join
        // condition literally checks `status = 'active'` — 'revoked'
        // would fail closed for the right reason but the wrong one.
        $this->makeStudentParentLink($school, $parent, $child, ['status' => 'inactive']);

        $response = $this->actingAs($parent)->get("/parent/children/{$child->id}");

        $response->assertNotFound();
    }

    public function test_parent_can_view_own_childs_profile(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $child = $this->makeUser([
            'role_id' => $this->makeRole('student')->id,
            'school_id' => $school->id,
            'name' => 'Profile Child',
            'registration_number' => 'REG-42',
        ]);

        $this->makeStudentParentLink($school, $parent, $child);

        $response = $this->actingAs($parent)->get("/parent/children/{$child->id}");

        $response->assertOk();
        $response->assertSee('Profile Child');
        $response->assertSee('REG-42');
    }
}
