<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * layouts/nav.blade.php's active-state coverage. Regression target: before
 * this pass, parent.dashboard's nav item was narrowed from the broad
 * 'parent.' prefix (which over-matched once Timetable/Announcements/
 * Notifications siblings were added) down to the single exact route
 * 'parent.dashboard', which silently stopped "My children" from
 * highlighting on a linked child's profile/attendance page. The 4-tuple nav
 * item shape now takes an array of routeIs() patterns per item instead of
 * one auto-suffixed prefix, so parent.dashboard can list both
 * 'parent.dashboard' and 'parent.children.*' without over-matching anything
 * else in that portal's nav.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_my_children_nav_item_stays_highlighted_on_a_childs_profile_page(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);
        $this->makeStudentParentLink($school, $parent, $student);

        $response = $this->actingAs($parent)->get("/parent/children/{$student->id}");

        $response->assertOk();
        $response->assertSee('href="'.route('parent.dashboard').'"', false);
        $response->assertSeeInOrder(['aria-current="page"', 'My children'], false);
    }

    public function test_my_children_nav_item_stays_highlighted_on_a_childs_attendance_page(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);
        $this->makeStudentParentLink($school, $parent, $student);

        $response = $this->actingAs($parent)->get("/parent/children/{$student->id}/attendance");

        $response->assertOk();
        $response->assertSeeInOrder(['aria-current="page"', 'My children'], false);
    }

    /**
     * Sibling isolation: "My children" being active on children.* routes
     * must not make Timetable also light up — the failure mode the old
     * broad 'parent.' prefix had before it was (over-)narrowed.
     */
    public function test_other_parent_nav_items_are_not_highlighted_on_a_childs_profile_page(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);
        $this->makeStudentParentLink($school, $parent, $student);

        $response = $this->actingAs($parent)->get("/parent/children/{$student->id}");

        $response->assertOk();
        $content = $response->getContent();

        // Isolate the Timetable <a> tag and confirm it carries no
        // aria-current, without asserting on whole-page occurrence counts
        // (the page's own content may legitimately mention "Timetable").
        $this->assertMatchesRegularExpression(
            '#<a\s+href="'.preg_quote(route('parent.timetable.index'), '#').'"[^>]*>(?:(?!aria-current)[\s\S])*?</a>#',
            $content
        );
    }
}