<?php

namespace Tests\Feature\StudentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §6
 *
 * StudentPortal\TimetableController's HTTP-layer coverage — the student
 * half of the same gap ParentPortal\TimetableControllerTest closes (see
 * that file's doc comment). A student's visibility is resolved through
 * their own current StudentEnrollment row(s), self-only (no linking
 * step, unlike parent) per ScopeService::studentRelationshipScope()'s
 * doc comment.
 */
class TimetableControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/student/timetable');

        $response->assertRedirect('/login');
    }

    public function test_non_student_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/student/timetable');

        $response->assertForbidden();
    }

    public function test_an_enrolled_student_can_index_and_show_their_own_class_sections_slots(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $indexResponse = $this->actingAs($student)->get('/student/timetable');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('student.timetable.index');
        $indexResponse->assertViewHas('slots', fn ($slots) => $slots->contains('id', $slot->id));

        $showResponse = $this->actingAs($student)->get("/student/timetable/{$slot->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('student.timetable.show');
        $showResponse->assertViewHas('slot', fn ($viewSlot) => $viewSlot->id === $slot->id);
    }

    /**
     * 404-not-403: an unrelated (unenrolled) student gets the same
     * response as a nonexistent slot id.
     */
    public function test_an_unrelated_student_gets_a_404_on_show(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);

        $unrelatedStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($unrelatedStudent)->get("/student/timetable/{$slot->id}");

        $response->assertNotFound();
    }
}
