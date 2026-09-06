<?php

namespace Tests\Feature\StudentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §6, §7
 *
 * StudentPortal\ExamController's HTTP-layer coverage — see
 * StudentPortal\TimetableControllerTest's doc comment for the shape
 * shared across all portal/resource test files this pass adds. Covers
 * Exam only, not ExamMark (still out of scope).
 */
class ExamControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/student/exams');

        $response->assertRedirect('/login');
    }

    public function test_non_student_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/student/exams');

        $response->assertForbidden();
    }

    public function test_an_enrolled_student_can_index_and_show_their_own_class_sections_exams(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $exam = $this->makeExam($school, $academicYear, $classSection, $subject);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $indexResponse = $this->actingAs($student)->get('/student/exams');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('student.exams.index');
        $indexResponse->assertViewHas('exams', fn ($exams) => $exams->contains('id', $exam->id));

        $showResponse = $this->actingAs($student)->get("/student/exams/{$exam->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('student.exams.show');
        $showResponse->assertViewHas('exam', fn ($viewExam) => $viewExam->id === $exam->id);
    }

    public function test_an_unrelated_student_gets_a_404_on_show(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $exam = $this->makeExam($school, $academicYear, $classSection, $subject);

        $unrelatedStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($unrelatedStudent)->get("/student/exams/{$exam->id}");

        $response->assertNotFound();
    }
}
