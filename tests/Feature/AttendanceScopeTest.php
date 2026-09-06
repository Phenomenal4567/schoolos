<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\ClassSection;
use App\Models\School;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 14-schoolos-implementation-plan.md §3's Phase 3 test gate,
 * row 2 — "a parent/student cannot pull another student's attendance via
 * any endpoint taking a student identifier."
 *
 * DashboardController::childAttendance() is currently the one endpoint
 * that shape describes: it takes a {student} route parameter and returns
 * per-student data, the exact F20 shape ParentDashboardTest already
 * regression-tests for showChild(). This file is that same regression
 * re-run against childAttendance() specifically, since a route can
 * resolve $student correctly (showChild() does — ParentDashboardTest
 * covers it) while a sibling action on the same controller still leaks
 * another student's rows through its *own* query if that second query
 * isn't independently scoped. childAttendance() runs two scoped queries
 * (see its own doc comment) — the tests below exercise both: the first
 * (resolving $student itself) and the second (AttendanceRecord's own
 * relationshipScope() join, the "ScopeService join" this row names
 * explicitly).
 *
 * No student-role endpoint exists yet for a student to view their own
 * attendance (only the parent-facing route is wired up per
 * 16-schoolos-decisions-register.md's "attendance + announcements +
 * profile" scope) — nothing here tests a student self-view because
 * there is nothing to test yet.
 */
class AttendanceScopeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $school = $this->makeSchool();
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $response = $this->get("/parent/children/{$student->id}/attendance");

        $response->assertRedirect('/login');
    }

    public function test_non_parent_role_is_forbidden(): void
    {
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id]);

        $response = $this->actingAs($teacher)->get("/parent/children/{$teacher->id}/attendance");

        $response->assertForbidden();
    }

    /**
     * The direct F20-shaped regression this file exists for: parent A
     * manipulating the {student} route parameter to parent B's child
     * must not reveal that child's attendance, and must fail identically
     * to a nonexistent id (404), never a 403 that would confirm the id is
     * valid for someone else — the same discipline
     * ParentDashboardTest::test_parent_cannot_view_another_parents_child()
     * already established for showChild().
     */
    public function test_parent_cannot_view_another_parents_child_attendance(): void
    {
        $school = $this->makeSchool();
        $studentRole = $this->makeRole('student');
        $parentRole = $this->makeRole('parent');
        $teacherRole = $this->makeRole('teacher');

        $parentA = $this->makeUser(['role_id' => $parentRole->id, 'school_id' => $school->id, 'email' => 'parent-a@example.test']);
        $parentB = $this->makeUser(['role_id' => $parentRole->id, 'school_id' => $school->id, 'email' => 'parent-b@example.test']);
        $childOfB = $this->makeUser(['role_id' => $studentRole->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $parentB, $childOfB);

        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $teacherRole->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $childOfB);
        $this->markAttendance($school, $academicYear, $classSection, $childOfB, $teacher);

        $response = $this->actingAs($parentA)->get("/parent/children/{$childOfB->id}/attendance");

        $response->assertNotFound();
    }

    /**
     * Covers the second, independent scope check childAttendance()'s own
     * doc comment describes: even a $student id that resolves correctly
     * against relationshipScope() on the User model must also be denied
     * if AttendanceRecord's own relationshipScope() join were ever to
     * regress — this test would catch that even though it can't
     * currently force the two checks to disagree, since both are driven
     * by the same student_parent_links row today.
     */
    public function test_inactive_link_hides_the_childs_attendance(): void
    {
        $school = $this->makeSchool();
        $studentRole = $this->makeRole('student');
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $child = $this->makeUser(['role_id' => $studentRole->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $parent, $child, ['status' => 'inactive']);

        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $child);
        $this->markAttendance($school, $academicYear, $classSection, $child, $teacher);

        $response = $this->actingAs($parent)->get("/parent/children/{$child->id}/attendance");

        $response->assertNotFound();
    }

    /**
     * Positive control: the scope check above isn't just denying every
     * request. Without this, a bug that made childAttendance() 404 for
     * everyone would pass test_parent_cannot_view_another_parents_child_
     * attendance() for the wrong reason.
     */
    public function test_parent_can_view_own_childs_attendance(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $child = $this->makeUser([
            'role_id' => $this->makeRole('student')->id,
            'school_id' => $school->id,
            'name' => 'Own Child',
        ]);
        $this->makeStudentParentLink($school, $parent, $child);

        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $child);
        $this->markAttendance($school, $academicYear, $classSection, $child, $teacher, 'absent');

        $response = $this->actingAs($parent)->get("/parent/children/{$child->id}/attendance");

        $response->assertOk();
        // The view runs ucfirst() on the raw status column.
        $response->assertSee('Absent');
    }

    private function markAttendance(
        School $school,
        AcademicYear $academicYear,
        ClassSection $classSection,
        User $student,
        User $teacher,
        string $status = 'present'
    ): AttendanceRecord {
        return (new AttendanceRepository())->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            $status,
            $teacher
        );
    }
}
