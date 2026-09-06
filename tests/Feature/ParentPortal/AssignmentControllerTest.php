<?php

namespace Tests\Feature\ParentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §6
 *
 * ParentPortal\AssignmentController's HTTP-layer coverage — see
 * ParentPortal\TimetableControllerTest's doc comment for the shape
 * shared across all portal/resource test files this pass adds. Covers
 * Assignment only, not AssignmentSubmission (still out of scope — see
 * AssignmentRepository's own doc comment).
 */
class AssignmentControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/parent/assignments');

        $response->assertRedirect('/login');
    }

    public function test_non_parent_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/parent/assignments');

        $response->assertForbidden();
    }

    public function test_a_linked_parent_can_index_and_show_their_childs_assignments(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $teacher);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $parent, $student);

        $indexResponse = $this->actingAs($parent)->get('/parent/assignments');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('parent.assignments.index');
        $indexResponse->assertViewHas('assignments', fn ($assignments) => $assignments->contains('id', $assignment->id));

        $showResponse = $this->actingAs($parent)->get("/parent/assignments/{$assignment->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('parent.assignments.show');
        $showResponse->assertViewHas('assignment', fn ($viewAssignment) => $viewAssignment->id === $assignment->id);
    }

    public function test_an_unlinked_parent_gets_a_404_on_show(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $teacher);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);
        $unlinkedParent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($unlinkedParent)->get("/parent/assignments/{$assignment->id}");

        $response->assertNotFound();
    }
}
