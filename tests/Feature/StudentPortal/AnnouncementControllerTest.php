<?php

namespace Tests\Feature\StudentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 18-schoolos-communication-domain-map.md §6, §8
 *
 * StudentPortal\AnnouncementController's HTTP-layer coverage — see
 * ParentPortal\AnnouncementControllerTest's doc comment for the shape
 * shared across portals.
 */
class AnnouncementControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/student/announcements');

        $response->assertRedirect('/login');
    }

    public function test_non_student_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/student/announcements');

        $response->assertForbidden();
    }

    public function test_an_enrolled_student_can_index_show_and_mark_read_their_class_section_announcement(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $teacher);
        $announcement = $this->makeAnnouncement($school, $teacher, 'class_section', $section);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $section, $student);

        $indexResponse = $this->actingAs($student)->get('/student/announcements');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('student.announcements.index');
        $indexResponse->assertViewHas('announcements', fn ($announcements) => $announcements->contains('id', $announcement->id));

        $showResponse = $this->actingAs($student)->get("/student/announcements/{$announcement->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('student.announcements.show');
        $showResponse->assertViewHas('announcement', fn ($viewAnnouncement) => $viewAnnouncement->id === $announcement->id);

        $readResponse = $this->actingAs($student)->post("/student/announcements/{$announcement->id}/read");
        $readResponse->assertOk();

        $this->assertDatabaseHas('content_read_receipts', [
            'user_id' => $student->id,
            'entity_type' => 'announcement',
            'entity_id' => $announcement->id,
        ]);
    }

    public function test_an_unrelated_student_gets_a_404_on_show_and_mark_read(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $teacher);
        $announcement = $this->makeAnnouncement($school, $teacher, 'class_section', $section);

        $unrelatedStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $this->actingAs($unrelatedStudent)->get("/student/announcements/{$announcement->id}")->assertNotFound();
        $this->actingAs($unrelatedStudent)->post("/student/announcements/{$announcement->id}/read")->assertNotFound();
    }
}
