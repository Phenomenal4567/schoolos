<?php

namespace Tests\Feature\TeacherPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §2, §3, §5, §6, §7
 *
 * TeacherPortal\ExamController's HTTP-layer coverage — see
 * TeacherPortal\TimetableControllerTest's doc comment for the shape
 * shared across all four generic Phase 4 resource controller test files.
 * Covers Exam only, not ExamMark — entering marks remains out of scope
 * for this pass (see ExamRepository's own doc comment). Exam carries no
 * teacher_id column at all (see that model's fillable list), so unlike
 * the other three resources' create tests, this one has nothing
 * teacher-attribution-shaped to assert beyond "the row exists, scoped to
 * the right school/year/section."
 */
class ExamControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/teacher/exams', []);

        $response->assertRedirect('/login');
    }

    public function test_non_teacher_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post('/teacher/exams', []);

        $response->assertForbidden();
    }

    /**
     * The "New exam" view is a plain `<form method="POST">` with no
     * fetch(), so a successful submit must redirect — see
     * TeacherPortal\TimetableControllerTest's identical
     * assertRedirect()-over-assertCreated() shape.
     */
    public function test_teacher_can_create_an_exam_for_their_own_assigned_class_section(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);

        $response = $this->actingAs($teacher)->post('/teacher/exams', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'name' => 'Midterm',
            'exam_date' => '2026-10-01',
        ]);

        $response->assertRedirect(route('teacher.exams.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('exams', [
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'name' => 'Midterm',
        ]);
    }

    /**
     * A fetch()/API caller (Accept: application/json, via postJson())
     * keeps the original JSON 201 contract untouched by the wantsJson()
     * branch above.
     */
    public function test_a_json_caller_still_gets_a_201_with_the_created_exam(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);

        $response = $this->actingAs($teacher)->postJson('/teacher/exams', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'name' => 'Midterm',
            'exam_date' => '2026-10-01',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.class_section_id', $classSection->id);
    }

    public function test_a_teacher_not_assigned_to_the_class_section_gets_a_404_not_a_403_or_422(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $ownerTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $ownerTeacher);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($otherTeacher)->post('/teacher/exams', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'name' => 'Not my section',
            'exam_date' => '2026-10-01',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('exams', 0);
    }

    public function test_teacher_can_index_and_show_exams_for_their_own_assigned_sections(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $exam = $this->makeExam($school, $academicYear, $classSection, $subject);

        $indexResponse = $this->actingAs($teacher)->get('/teacher/exams');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('teacher.exams.index');
        $indexResponse->assertViewHas('exams', fn ($exams) => $exams->contains('id', $exam->id));

        $showResponse = $this->actingAs($teacher)->get("/teacher/exams/{$exam->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('teacher.exams.show');
        $showResponse->assertViewHas('exam', fn ($viewExam) => $viewExam->id === $exam->id);
    }

    public function test_a_teacher_with_no_assignment_to_the_exams_section_gets_a_404_on_show(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $ownerTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $ownerTeacher);
        $exam = $this->makeExam($school, $academicYear, $classSection, $subject);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($otherTeacher)->get("/teacher/exams/{$exam->id}");

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

        $response = $this->actingAs($teacher)->post('/teacher/exams', [
            'class_section_id' => $classSectionInOtherSchool->id,
            'subject_id' => $subject->id,
            'name' => 'Cross tenant',
            'exam_date' => '2026-10-01',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('class_section_id');
        $this->assertDatabaseCount('exams', 0);
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

        $response = $this->actingAs($teacher)->postJson('/teacher/exams', [
            'class_section_id' => $classSectionInOtherSchool->id,
            'subject_id' => $subject->id,
            'name' => 'Cross tenant',
            'exam_date' => '2026-10-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['class_section_id']]);
        $this->assertDatabaseCount('exams', 0);
    }

    /**
     * Each enrolled student's marks-entry form on the exam show page is
     * a plain `<form method="POST">` with no fetch(), so a successful
     * submit must redirect back to that page — see
     * TeacherPortal\TimetableControllerTest's identical
     * assertRedirect()-over-assertCreated()-with-JSON shape.
     */
    public function test_the_assigned_teacher_can_record_a_mark_for_an_enrolled_student(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $exam = $this->makeExam($school, $academicYear, $classSection, $subject);
        $component = $this->makeExamComponent($school);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $response = $this->actingAs($teacher)->post('/teacher/exam-marks', [
            'exam_id' => $exam->id,
            'exam_component_id' => $component->id,
            'student_id' => $student->id,
            'marks_obtained' => 72.5,
            'max_marks' => 100,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('exam_marks', [
            'exam_id' => $exam->id,
            'exam_component_id' => $component->id,
            'student_id' => $student->id,
            'marks_obtained' => 72.5,
            'recorded_by' => $teacher->id,
            'status' => 'draft',
        ]);
    }

    /**
     * A fetch()/API caller (Accept: application/json, via postJson())
     * keeps the original JSON 201 contract untouched by the wantsJson()
     * branch above.
     */
    public function test_a_json_caller_still_gets_a_201_with_the_recorded_mark(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $exam = $this->makeExam($school, $academicYear, $classSection, $subject);
        $component = $this->makeExamComponent($school);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $response = $this->actingAs($teacher)->postJson('/teacher/exam-marks', [
            'exam_id' => $exam->id,
            'exam_component_id' => $component->id,
            'student_id' => $student->id,
            'marks_obtained' => 72.5,
            'max_marks' => 100,
        ]);

        $response->assertCreated();
    }

    public function test_a_teacher_not_assigned_to_the_exams_section_gets_a_404_recording_a_mark(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $ownerTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $ownerTeacher);
        $exam = $this->makeExam($school, $academicYear, $classSection, $subject);
        $component = $this->makeExamComponent($school);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($otherTeacher)->post('/teacher/exam-marks', [
            'exam_id' => $exam->id,
            'exam_component_id' => $component->id,
            'student_id' => $student->id,
            'marks_obtained' => 50,
            'max_marks' => 100,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('exam_marks', 0);
    }

    public function test_a_student_not_enrolled_in_the_exams_section_is_rejected_with_a_422(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $exam = $this->makeExam($school, $academicYear, $classSection, $subject);
        $component = $this->makeExamComponent($school);
        $unenrolledStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($teacher)->post('/teacher/exam-marks', [
            'exam_id' => $exam->id,
            'exam_component_id' => $component->id,
            'student_id' => $unenrolledStudent->id,
            'marks_obtained' => 50,
            'max_marks' => 100,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('exam_id');
        $this->assertDatabaseCount('exam_marks', 0);
    }

    /**
     * Same repository-level rejection as the test above, through the
     * JSON path a fetch()/API caller uses instead of session flash.
     */
    public function test_a_json_caller_gets_a_422_for_an_unenrolled_student(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $exam = $this->makeExam($school, $academicYear, $classSection, $subject);
        $component = $this->makeExamComponent($school);
        $unenrolledStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($teacher)->postJson('/teacher/exam-marks', [
            'exam_id' => $exam->id,
            'exam_component_id' => $component->id,
            'student_id' => $unenrolledStudent->id,
            'marks_obtained' => 50,
            'max_marks' => 100,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('exam_marks', 0);
    }
}
