<?php

namespace Tests\Feature\TeacherPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 18-schoolos-communication-domain-map.md §6, §8
 *
 * TeacherPortal\AnnouncementController's HTTP-layer coverage — see
 * ParentPortal\AnnouncementControllerTest's doc comment for the
 * index/show/markRead shape shared across portals. This controller also
 * has store() (class_section-only, must be assigned) — covered here too,
 * unlike the read-only parent/student portals.
 */
class AnnouncementControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/teacher/announcements');

        $response->assertRedirect('/login');
    }

    public function test_non_teacher_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/teacher/announcements');

        $response->assertForbidden();
    }

    public function test_an_assigned_teacher_can_index_show_and_mark_read_their_section_announcement(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $teacher);
        $announcement = $this->makeAnnouncement($school, $teacher, 'class_section', $section);

        $indexResponse = $this->actingAs($teacher)->get('/teacher/announcements');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('teacher.announcements.index');
        $indexResponse->assertViewHas('announcements', fn ($announcements) => $announcements->contains('id', $announcement->id));

        $showResponse = $this->actingAs($teacher)->get("/teacher/announcements/{$announcement->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('teacher.announcements.show');
        $showResponse->assertViewHas('announcement', fn ($viewAnnouncement) => $viewAnnouncement->id === $announcement->id);

        $readResponse = $this->actingAs($teacher)->post("/teacher/announcements/{$announcement->id}/read");
        $readResponse->assertOk();

        $this->assertDatabaseHas('content_read_receipts', [
            'user_id' => $teacher->id,
            'entity_type' => 'announcement',
            'entity_id' => $announcement->id,
        ]);
    }

    /**
     * Closes the "no pagination on announcements" gap: with more
     * school-wide announcements than one page holds, index() must
     * paginate rather than dump every row, and the second page must be
     * reachable via the standard ?page= query param.
     */
    public function test_the_announcements_index_is_paginated(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        for ($i = 0; $i < 25; $i++) {
            $this->makeAnnouncement($school, $admin, 'school');
        }

        $firstPage = $this->actingAs($teacher)->get('/teacher/announcements');
        $firstPage->assertOk();
        $firstPage->assertViewHas('announcements', fn ($announcements) => $announcements->count() === 20
            && $announcements->total() === 25
            && $announcements->hasMorePages());

        $secondPage = $this->actingAs($teacher)->get('/teacher/announcements?page=2');
        $secondPage->assertOk();
        $secondPage->assertViewHas('announcements', fn ($announcements) => $announcements->count() === 5);
    }

    public function test_an_unassigned_teacher_gets_a_404_on_show_and_mark_read(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $teacher);
        $announcement = $this->makeAnnouncement($school, $teacher, 'class_section', $section);

        $unassignedTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $this->actingAs($unassignedTeacher)->get("/teacher/announcements/{$announcement->id}")->assertNotFound();
        $this->actingAs($unassignedTeacher)->post("/teacher/announcements/{$announcement->id}/read")->assertNotFound();
    }

    /**
     * The "Post to my class" view is a plain `<form method="POST">` with
     * no fetch(), so a successful submit must redirect (per
     * AnnouncementController::store()'s own doc comment) rather than
     * navigate the browser to a raw JSON body.
     */
    public function test_teacher_can_store_a_class_section_announcement_for_their_assigned_section(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $teacher);

        $response = $this->actingAs($teacher)->post('/teacher/announcements', [
            'title' => 'Field trip',
            'body' => 'Bring a permission slip.',
            'class_section_id' => $section->id,
        ]);

        $response->assertRedirect(route('teacher.announcements.index'));
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('announcements', [
            'title' => 'Field trip',
            'class_section_id' => $section->id,
            'author_id' => $teacher->id,
        ]);
    }

    /**
     * A fetch()/API caller (Accept: application/json, via postJson())
     * keeps the original JSON 201 contract untouched by the
     * wantsJson() branch in store().
     */
    public function test_a_json_caller_still_gets_a_201_with_the_created_announcement(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $teacher);

        $response = $this->actingAs($teacher)->postJson('/teacher/announcements', [
            'title' => 'Field trip',
            'body' => 'Bring a permission slip.',
            'class_section_id' => $section->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Field trip');
    }

    public function test_teacher_gets_a_404_storing_an_announcement_for_an_unassigned_section(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $sectionOwner = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $sectionOwner);
        $unassignedTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($unassignedTeacher)->post('/teacher/announcements', [
            'title' => 'Field trip',
            'body' => 'Bring a permission slip.',
            'class_section_id' => $section->id,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('announcements', ['title' => 'Field trip']);
    }
}