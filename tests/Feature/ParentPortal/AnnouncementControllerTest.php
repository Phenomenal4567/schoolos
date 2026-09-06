<?php

namespace Tests\Feature\ParentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 18-schoolos-communication-domain-map.md §6, §8
 *
 * ParentPortal\AnnouncementController's HTTP-layer coverage — ordinary
 * per-endpoint coverage, matching ParentPortal\AssignmentControllerTest's
 * shape; Phase5TestGateTest.php is the scope-correctness gate, this is
 * the real-HTTP-request confirmation every other controller already has.
 */
class AnnouncementControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/parent/announcements');

        $response->assertRedirect('/login');
    }

    public function test_non_parent_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/parent/announcements');

        $response->assertForbidden();
    }

    public function test_a_linked_parent_can_index_show_and_mark_read_their_childs_class_section_announcement(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $teacher);
        $announcement = $this->makeAnnouncement($school, $teacher, 'class_section', $section);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $section, $student);
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $parent, $student);

        $indexResponse = $this->actingAs($parent)->get('/parent/announcements');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('parent.announcements.index');
        $indexResponse->assertViewHas('announcements', fn ($announcements) => $announcements->contains('id', $announcement->id));

        $showResponse = $this->actingAs($parent)->get("/parent/announcements/{$announcement->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('parent.announcements.show');
        $showResponse->assertViewHas('announcement', fn ($viewAnnouncement) => $viewAnnouncement->id === $announcement->id);

        $readResponse = $this->actingAs($parent)->post("/parent/announcements/{$announcement->id}/read");
        $readResponse->assertOk();

        $this->assertDatabaseHas('content_read_receipts', [
            'user_id' => $parent->id,
            'entity_type' => 'announcement',
            'entity_id' => $announcement->id,
        ]);
    }

    public function test_an_unlinked_parent_gets_a_404_on_show_and_mark_read(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $teacher);
        $announcement = $this->makeAnnouncement($school, $teacher, 'class_section', $section);

        $unlinkedParent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);

        $this->actingAs($unlinkedParent)->get("/parent/announcements/{$announcement->id}")->assertNotFound();
        $this->actingAs($unlinkedParent)->post("/parent/announcements/{$announcement->id}/read")->assertNotFound();
    }
}
