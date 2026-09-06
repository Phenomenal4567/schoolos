<?php

namespace Tests\Feature\ParentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §6, §7
 *
 * ParentPortal\ExamController's HTTP-layer coverage — see
 * ParentPortal\TimetableControllerTest's doc comment for the shape
 * shared across all portal/resource test files this pass adds. Covers
 * Exam only, not ExamMark (still out of scope).
 */
class ExamControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/parent/exams');

        $response->assertRedirect('/login');
    }

    public function test_non_parent_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/parent/exams');

        $response->assertForbidden();
    }

    public function test_a_linked_parent_can_index_and_show_their_childs_exams(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $exam = $this->makeExam($school, $academicYear, $classSection, $subject);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $parent, $student);

        $indexResponse = $this->actingAs($parent)->get('/parent/exams');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('parent.exams.index');
        $indexResponse->assertViewHas('exams', fn ($exams) => $exams->contains('id', $exam->id));

        $showResponse = $this->actingAs($parent)->get("/parent/exams/{$exam->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('parent.exams.show');
        $showResponse->assertViewHas('exam', fn ($viewExam) => $viewExam->id === $exam->id);
    }

    public function test_an_unlinked_parent_gets_a_404_on_show(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $exam = $this->makeExam($school, $academicYear, $classSection, $subject);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);
        $unlinkedParent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($unlinkedParent)->get("/parent/exams/{$exam->id}");

        $response->assertNotFound();
    }
}
