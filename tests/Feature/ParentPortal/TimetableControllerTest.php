<?php

namespace Tests\Feature\ParentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §6
 *
 * ParentPortal\TimetableController's HTTP-layer coverage — the exact gap
 * this pass exists to close for the parent/student read side: 17 §6's
 * table's "A parent/student cannot fetch another student's ... timetable
 * via any endpoint that takes an ID" row was previously only exercised
 * against ScopeService directly (Phase4TestGateTest), never through the
 * real HTTP routes, because no controller/route existed for this
 * resource until this pass. See TeacherPortal\TimetableControllerTest's
 * doc comment for the shared shape across all portal/resource test
 * files.
 */
class TimetableControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/parent/timetable');

        $response->assertRedirect('/login');
    }

    public function test_non_parent_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/parent/timetable');

        $response->assertForbidden();
    }

    public function test_a_linked_parent_can_index_and_show_their_childs_class_sections_slots(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $parent, $student);

        $indexResponse = $this->actingAs($parent)->get('/parent/timetable');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('parent.timetable.index');
        $indexResponse->assertViewHas('slots', fn ($slots) => $slots->contains('id', $slot->id));

        $showResponse = $this->actingAs($parent)->get("/parent/timetable/{$slot->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('parent.timetable.show');
        $showResponse->assertViewHas('slot', fn ($viewSlot) => $viewSlot->id === $slot->id);
    }

    /**
     * 404-not-403: an unlinked parent gets the same response as a
     * nonexistent slot id, never a signal the id is valid for someone
     * else's child.
     */
    public function test_an_unlinked_parent_gets_a_404_on_show(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);
        $unlinkedParent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($unlinkedParent)->get("/parent/timetable/{$slot->id}");

        $response->assertNotFound();
    }
}
