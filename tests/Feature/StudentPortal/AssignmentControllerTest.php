<?php

namespace Tests\Feature\StudentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §5, §6
 *
 * StudentPortal\AssignmentController's HTTP-layer coverage — see
 * StudentPortal\TimetableControllerTest's doc comment for the shape
 * shared across all portal/resource test files this pass adds. submit()
 * tests (this pass's addition) cover the write side of
 * AssignmentSubmission the earlier Phase 4 pass left open.
 */
class AssignmentControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/student/assignments');

        $response->assertRedirect('/login');
    }

    public function test_non_student_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/student/assignments');

        $response->assertForbidden();
    }

    public function test_an_enrolled_student_can_index_and_show_their_own_class_sections_assignments(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $teacher);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $indexResponse = $this->actingAs($student)->get('/student/assignments');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('student.assignments.index');
        $indexResponse->assertViewHas('assignments', fn ($assignments) => $assignments->contains('id', $assignment->id));

        $showResponse = $this->actingAs($student)->get("/student/assignments/{$assignment->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('student.assignments.show');
        $showResponse->assertViewHas('assignment', fn ($viewAssignment) => $viewAssignment->id === $assignment->id);
    }

    public function test_an_unrelated_student_gets_a_404_on_show(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $teacher);

        $unrelatedStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($unrelatedStudent)->get("/student/assignments/{$assignment->id}");

        $response->assertNotFound();
    }

    /**
     * The submission form on the assignment show page is a plain
     * `<form method="POST">` with no fetch(), so a successful submit
     * must redirect back to that page — see
     * TeacherPortal\TimetableControllerTest's identical
     * assertRedirect()-over-assertCreated() shape.
     */
    public function test_an_enrolled_student_can_submit_against_their_own_class_sections_assignment(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $teacher);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $response = $this->actingAs($student)->post("/student/assignments/{$assignment->id}/submissions", [
            'comments' => 'Attached my worksheet.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'comments' => 'Attached my worksheet.',
        ]);
    }

    /**
     * A fetch()/API caller (Accept: application/json, via postJson())
     * keeps the original JSON 201 contract untouched by the wantsJson()
     * branch above.
     */
    public function test_a_json_caller_still_gets_a_201_with_the_created_submission(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $teacher);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $response = $this->actingAs($student)->postJson("/student/assignments/{$assignment->id}/submissions", [
            'comments' => 'Attached my worksheet.',
        ]);

        $response->assertCreated();
    }

    public function test_resubmitting_updates_the_same_row_not_a_second_one(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $teacher);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $this->actingAs($student)->post("/student/assignments/{$assignment->id}/submissions", [
            'comments' => 'First attempt.',
        ]);
        $this->actingAs($student)->post("/student/assignments/{$assignment->id}/submissions", [
            'comments' => 'Second attempt.',
        ]);

        $this->assertDatabaseCount('assignment_submissions', 1);
        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'comments' => 'Second attempt.',
        ]);
    }

    public function test_an_unrelated_student_gets_a_404_on_submit(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $teacher);

        $unrelatedStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($unrelatedStudent)->post("/student/assignments/{$assignment->id}/submissions", []);

        $response->assertNotFound();
        $this->assertDatabaseCount('assignment_submissions', 0);
    }
}
