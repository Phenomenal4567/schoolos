<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Rich Teacher/Staff
 * Enrollment And Profiles").
 *
 * Admin\StaffProfileController is read-only oversight, tenant-scoped
 * through ScopeService::tenantScope() the same way every other
 * school_admin single-record route is — central assertion is that a
 * school_admin cannot view or download another school's staff profile.
 */
class StaffProfileControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_school_admin_can_view_a_staff_members_profile(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $teacher = $this->makeRoleUser('teacher', $school, ['name' => 'Ada Teacher']);
        $this->makeStaffProfile($school, $teacher, [
            'qualifications' => ['B.Sc. Mathematics'],
            'responsibilities' => 'Head of Mathematics department.',
        ]);

        $response = $this->actingAs($admin)->get("/admin/staff/{$teacher->id}/profile");

        $response->assertOk();
        $response->assertSee('Ada Teacher');
        $response->assertSee('Head of Mathematics department.');
        $response->assertSee('B.Sc. Mathematics');
    }

    public function test_school_admin_cannot_view_another_schools_staff_profile(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $otherSchool = $this->makeSchool();
        $otherTeacher = $this->makeRoleUser('teacher', $otherSchool);

        $this->actingAs($admin)
            ->get("/admin/staff/{$otherTeacher->id}/profile")
            ->assertNotFound();
    }

    public function test_school_admin_can_download_a_staff_members_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('staff-documents/cert.pdf', 'content');

        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $document = $this->makeStaffDocument($school, $teacher, ['file_path' => 'staff-documents/cert.pdf']);

        $this->actingAs($admin)
            ->get("/admin/staff/{$teacher->id}/profile/documents/{$document->id}/download")
            ->assertOk();
    }

    public function test_school_admin_cannot_download_another_schools_staff_document(): void
    {
        Storage::fake('local');

        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $otherSchool = $this->makeSchool();
        $otherTeacher = $this->makeRoleUser('teacher', $otherSchool);
        $document = $this->makeStaffDocument($otherSchool, $otherTeacher);

        $this->actingAs($admin)
            ->get("/admin/staff/{$otherTeacher->id}/profile/documents/{$document->id}/download")
            ->assertNotFound();
    }
}
