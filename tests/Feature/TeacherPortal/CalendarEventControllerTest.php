<?php

namespace Tests\Feature\TeacherPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

class CalendarEventControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/teacher/calendar-events');

        $response->assertRedirect('/login');
    }

    public function test_non_teacher_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->get('/teacher/calendar-events');

        $response->assertForbidden();
    }

    public function test_teacher_can_view_their_schools_calendar_events(): void
    {
        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $event = $this->makeCalendarEvent($school, $year, ['title' => 'Mid-Term Break']);

        $indexResponse = $this->actingAs($teacher)->get('/teacher/calendar-events');

        $indexResponse->assertOk();
        $indexResponse->assertViewIs('teacher.calendar-events.index');
        $indexResponse->assertViewHas('events', fn ($events) => $events->contains('id', $event->id));

        $showResponse = $this->actingAs($teacher)->get("/teacher/calendar-events/{$event->id}");

        $showResponse->assertOk();
        $showResponse->assertViewIs('teacher.calendar-events.show');
        $showResponse->assertViewHas('event', fn ($viewEvent) => $viewEvent->id === $event->id);
    }

    public function test_teacher_cannot_view_another_schools_calendar_events(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);
        $otherYear = $this->makeAcademicYear($otherSchool);
        $otherEvent = $this->makeCalendarEvent($otherSchool, $otherYear, ['title' => 'Other School Event']);

        $indexResponse = $this->actingAs($teacher)->get('/teacher/calendar-events');

        $indexResponse->assertOk();
        $indexResponse->assertViewHas('events', fn ($events) => ! $events->contains('id', $otherEvent->id));

        $this->actingAs($teacher)
            ->get("/teacher/calendar-events/{$otherEvent->id}")
            ->assertNotFound();
    }
}
