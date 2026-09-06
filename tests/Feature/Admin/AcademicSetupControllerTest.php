<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: this session's own task doc ("SchoolOS — Admin Setup Write
 * Paths + School Admin Dashboard"), mirroring
 * Admin\EnrollmentControllerTest's structure.
 *
 * Combined into one file rather than four (AcademicYearControllerTest /
 * StandardControllerTest / SectionControllerTest / SubjectControllerTest):
 * the four controllers are identically shaped (required string field(s),
 * no cross-role invariant, no route parameter) and the whole point of
 * that shape is that there's nothing resource-specific to say about any
 * one of them in isolation — four near-identical files would just repeat
 * the same test names with different route/table names. One file per
 * repeated shape reads more honestly than four files that all say the
 * same thing.
 *
 * subjects was added in a later session (15-academic-domain-map.md §2,
 * Phase 4's first slice) as a fifth row in endpoints() rather than a new
 * file — same shape confirmed by extending the existing provider without
 * needing to touch any of the four test methods themselves. subjects'
 * payload includes an extra 'code' field the other three don't have;
 * this doesn't require a method change since every assertion here keys
 * off $field/$expectedValue (both set to 'name'/'Mathematics' for this
 * row) and ignores any other payload keys.
 */
class AcademicSetupControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public static function endpoints(): array
    {
        return [
            'academic years' => ['/admin/academic-years', 'academic_years', ['label' => '2026-2027'], 'label', '2026-2027'],
            'standards' => ['/admin/standards', 'standards', ['name' => 'Grade 5'], 'name', 'Grade 5'],
            'sections' => ['/admin/sections', 'sections', ['name' => 'A'], 'name', 'A'],
            'subjects' => ['/admin/subjects', 'subjects', ['name' => 'Mathematics', 'code' => 'MATH'], 'name', 'Mathematics'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_guest_is_redirected_to_login(
        string $uri,
        string $table,
        array $payload,
        string $field,
        string $expectedValue
    ): void {
        $response = $this->post($uri, []);

        $response->assertRedirect('/login');
    }

    #[DataProvider('endpoints')]
    public function test_non_admin_role_is_forbidden(
        string $uri,
        string $table,
        array $payload,
        string $field,
        string $expectedValue
    ): void {
        $school = $this->makeSchool();
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($teacher)->post($uri, $payload);

        $response->assertForbidden();
        $this->assertDatabaseCount($table, 0);
    }

    #[DataProvider('endpoints')]
    public function test_admin_can_create_a_row_scoped_to_their_own_school(
        string $uri,
        string $table,
        array $payload,
        string $field,
        string $expectedValue
    ): void {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post($uri, $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas($table, array_merge(
            [$field => $expectedValue, 'school_id' => $school->id],
        ));
    }

    /**
     * The actual regression this task exists to prevent: none of these
     * four controllers accept school_id from the request at all (see
     * each controller's doc comment — it's always $actor->school_id), so
     * a school_admin trying to smuggle a different school_id in the
     * request body has no effect. Confirmed here rather than assumed
     * from reading the controller, the same way
     * ParentLinkControllerTest::test_admin_cannot_unlink_another_schools_link()
     * confirms its own cross-tenant guard by exercise rather than by
     * code review alone.
     */
    #[DataProvider('endpoints')]
    public function test_school_id_in_the_request_body_is_ignored(
        string $uri,
        string $table,
        array $payload,
        string $field,
        string $expectedValue
    ): void {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post($uri, array_merge($payload, [
            'school_id' => $otherSchool->id,
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas($table, [$field => $expectedValue, 'school_id' => $school->id]);
        $this->assertDatabaseMissing($table, [$field => $expectedValue, 'school_id' => $otherSchool->id]);
    }
}
