<?php

namespace Tests\Feature\TeacherPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: Timetable Management module.
 *
 * TeacherPortal\TimetableController's HTTP-layer coverage — read-only
 * now that Admin\TimetableController owns the write path (see that
 * controller's and TeacherPortal\TimetableController's own doc
 * comments). The former store()/create-a-slot tests this file used to
 * carry have been removed along with the route/method they exercised;
 * only index()/show() and their scoping behavior remain to test here.
 */
class TimetableControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/teacher/timetable');

        $response->assertRedirect('/login');
    }

    public function test_non_teacher_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/teacher/timetable');

        $response->assertForbidden();
    }

    public function test_teacher_can_index_and_show_slots_for_their_own_assigned_sections(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);

        $indexResponse = $this->actingAs($teacher)->get('/teacher/timetable');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('teacher.timetable.index');
        $indexResponse->assertViewHas('slots', fn ($slots) => $slots->contains('id', $slot->id));

        $showResponse = $this->actingAs($teacher)->get("/teacher/timetable/{$slot->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('teacher.timetable.show');
        $showResponse->assertViewHas('slot', fn ($viewSlot) => $viewSlot->id === $slot->id);
    }

    public function test_a_teacher_with_no_assignment_to_the_slots_section_gets_a_404_on_show(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $ownerTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $ownerTeacher);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $ownerTeacher);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($otherTeacher)->get("/teacher/timetable/{$slot->id}");

        $response->assertNotFound();
    }

    /**
     * The removed write path stays removed: no POST route exists for
     * /teacher/timetable any more. The URI still matches the surviving
     * GET /teacher/timetable route, so Laravel reports 405 (method not
     * allowed) rather than 404.
     */
    public function test_teacher_cannot_post_to_the_removed_store_route(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($teacher)->post('/teacher/timetable', []);

        $response->assertMethodNotAllowed();
    }
}
