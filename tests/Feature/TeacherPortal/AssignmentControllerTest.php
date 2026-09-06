<?php

namespace Tests\Feature\TeacherPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §2, §3, §5, §6
 *
 * TeacherPortal\AssignmentController's HTTP-layer coverage — see
 * TeacherPortal\TimetableControllerTest's doc comment for the shape
 * shared across all four generic Phase 4 resource controller test files.
 * grade() tests (this pass's addition) cover the F29 fix at the HTTP
 * layer — ScopeService's own branch is unit-tested directly in
 * Phase4TestGateTest, this file confirms the controller/route wiring
 * around it behaves the same way.
 */
class AssignmentControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/teacher/assignments', []);

        $response->assertRedirect('/login');
    }

    public function test_non_teacher_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post('/teacher/assignments', []);

        $response->assertForbidden();
    }

    /**
     * The "New assignment" view is a plain `<form method="POST">` with
     * no fetch(), so a successful submit must redirect — see
     * TeacherPortal\TimetableControllerTest's identical
     * assertRedirect()-over-assertCreated() shape.
     */
    public function test_teacher_can_create_an_assignment_for_their_own_assigned_class_section(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);

        $response = $this->actingAs($teacher)->post('/teacher/assignments', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'title' => 'Fractions worksheet',
            'description' => 'Complete odd-numbered problems.',
            'due_date' => '2026-09-10',
        ]);

        $response->assertRedirect(route('teacher.assignments.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('assignments', [
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Fractions worksheet',
        ]);
    }

    /**
     * A fetch()/API caller (Accept: application/json, via postJson())
     * keeps the original JSON 201 contract untouched by the wantsJson()
     * branch above.
     */
    public function test_a_json_caller_still_gets_a_201_with_the_created_assignment(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);

        $response = $this->actingAs($teacher)->postJson('/teacher/assignments', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'title' => 'Fractions worksheet',
            'description' => 'Complete odd-numbered problems.',
            'due_date' => '2026-09-10',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.class_section_id', $classSection->id);
        $response->assertJsonPath('data.teacher_id', $teacher->id);
    }

    public function test_a_teacher_not_assigned_to_the_class_section_gets_a_404_not_a_403_or_422(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $ownerTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $ownerTeacher);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($otherTeacher)->post('/teacher/assignments', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'title' => 'Not my section',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_teacher_can_index_and_show_assignments_for_their_own_assigned_sections(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $teacher);

        $indexResponse = $this->actingAs($teacher)->get('/teacher/assignments');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('teacher.assignments.index');
        $indexResponse->assertViewHas('assignments', fn ($assignments) => $assignments->contains('id', $assignment->id));

        $showResponse = $this->actingAs($teacher)->get("/teacher/assignments/{$assignment->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('teacher.assignments.show');
        $showResponse->assertViewHas('assignment', fn ($viewAssignment) => $viewAssignment->id === $assignment->id);
    }

    public function test_a_teacher_with_no_assignment_to_the_assignments_section_gets_a_404_on_show(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $ownerTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $ownerTeacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $ownerTeacher);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($otherTeacher)->get("/teacher/assignments/{$assignment->id}");

        $response->assertNotFound();
    }

    /**
     * Cross-tenant: see TimetableControllerTest's identical test for why
     * $classSection is deliberately built with $teacher as its homeroom
     * teacher despite belonging to $otherSchool.
     */
    public function test_a_class_section_from_another_school_is_rejected_with_a_422(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $otherAcademicYear = $this->makeAcademicYear($otherSchool);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $subject = $this->makeSubject($school);
        $classSectionInOtherSchool = $this->makeClassSection($otherSchool, $otherAcademicYear, $teacher);

        $response = $this->actingAs($teacher)->post('/teacher/assignments', [
            'class_section_id' => $classSectionInOtherSchool->id,
            'subject_id' => $subject->id,
            'title' => 'Cross tenant',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('class_section_id');
        $this->assertDatabaseCount('assignments', 0);
    }

    /**
     * Same repository-level rejection as the test above, through the
     * JSON path a fetch()/API caller uses instead of session flash.
     */
    public function test_a_json_caller_gets_a_422_for_a_class_section_from_another_school(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $otherAcademicYear = $this->makeAcademicYear($otherSchool);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $subject = $this->makeSubject($school);
        $classSectionInOtherSchool = $this->makeClassSection($otherSchool, $otherAcademicYear, $teacher);

        $response = $this->actingAs($teacher)->postJson('/teacher/assignments', [
            'class_section_id' => $classSectionInOtherSchool->id,
            'subject_id' => $subject->id,
            'title' => 'Cross tenant',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['class_section_id']]);
        $this->assertDatabaseCount('assignments', 0);
    }

    /**
     * The per-submission "Grade" form on the assignment show page is a
     * plain `<form method="POST">` with no fetch(), so a successful
     * submit must redirect back to that page — see
     * TeacherPortal\TimetableControllerTest's identical
     * assertRedirect()-over-assertOk()-with-JSON shape.
     */
    public function test_the_assigned_teacher_can_grade_a_submission_on_their_own_section(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $submission = $this->makeAssignmentSubmission($school, $assignment, $student);

        $response = $this->actingAs($teacher)->post("/teacher/submissions/{$submission->id}/grade", [
            'obtained_marks' => 8.5,
            'comments' => 'Good work.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'obtained_marks' => 8.5,
            'comments' => 'Good work.',
            'graded_by' => $teacher->id,
        ]);
    }

    /**
     * A fetch()/API caller (Accept: application/json, via postJson())
     * keeps the original JSON contract untouched by the wantsJson()
     * branch above.
     */
    public function test_a_json_caller_still_gets_the_graded_submission(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $submission = $this->makeAssignmentSubmission($school, $assignment, $student);

        $response = $this->actingAs($teacher)->postJson("/teacher/submissions/{$submission->id}/grade", [
            'obtained_marks' => 8.5,
            'comments' => 'Good work.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.obtained_marks', '8.50');
    }

    public function test_a_teacher_assigned_to_a_different_section_gets_a_404_grading_a_submission(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $ownerTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $ownerTeacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $ownerTeacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $submission = $this->makeAssignmentSubmission($school, $assignment, $student);

        $differentSectionTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $this->makeClassSection($school, $academicYear, $differentSectionTeacher);

        $response = $this->actingAs($differentSectionTeacher)->post("/teacher/submissions/{$submission->id}/grade", [
            'obtained_marks' => 5,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'obtained_marks' => null,
            'graded_by' => null,
        ]);
    }
}
