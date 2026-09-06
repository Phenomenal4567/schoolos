<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5,
 * 18-schoolos-communication-domain-map.md §8
 *
 * Admin\AnnouncementController's HTTP-layer coverage — store-only (no
 * index/show on this portal, per routes/web.php's own doc comment).
 */
class AnnouncementControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/admin/announcements', []);

        $response->assertRedirect('/login');
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($teacher)->post('/admin/announcements', [
            'title' => 'x',
            'body' => 'y',
            'audience_type' => 'school',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_store_a_school_wide_announcement(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post('/admin/announcements', [
            'title' => 'Holiday notice',
            'body' => 'School closed Friday.',
            'audience_type' => 'school',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('announcements', [
            'title' => 'Holiday notice',
            'audience_type' => 'school',
            'school_id' => $school->id,
            'author_id' => $admin->id,
        ]);
    }

    public function test_admin_can_store_a_class_section_announcement_for_any_section_in_their_school(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $teacher);

        $response = $this->actingAs($admin)->post('/admin/announcements', [
            'title' => 'Section notice',
            'body' => 'Test tomorrow.',
            'audience_type' => 'class_section',
            'class_section_id' => $section->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('announcements', [
            'title' => 'Section notice',
            'class_section_id' => $section->id,
        ]);
    }

    public function test_invalid_audience_type_is_rejected_with_422(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post('/admin/announcements', [
            'title' => 'x',
            'body' => 'y',
            'audience_type' => 'bogus',
        ]);

        $response->assertStatus(422);
    }
}
