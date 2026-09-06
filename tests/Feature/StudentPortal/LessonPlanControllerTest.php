<?php

namespace Tests\Feature\StudentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §6
 *
 * StudentPortal\LessonPlanController's HTTP-layer coverage — see
 * StudentPortal\TimetableControllerTest's doc comment for the shape
 * shared across all portal/resource test files this pass adds.
 */
class LessonPlanControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/student/lesson-plans');

        $response->assertRedirect('/login');
    }

    public function test_non_student_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/student/lesson-plans');

        $response->assertForbidden();
    }

    public function test_an_enrolled_student_can_index_and_show_their_own_class_sections_lesson_plans(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $lessonPlan = $this->makeLessonPlan($school, $academicYear, $classSection, $subject, $teacher);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $indexResponse = $this->actingAs($student)->get('/student/lesson-plans');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('student.lesson-plans.index');
        $indexResponse->assertViewHas('lessonPlans', fn ($lessonPlans) => $lessonPlans->contains('id', $lessonPlan->id));

        $showResponse = $this->actingAs($student)->get("/student/lesson-plans/{$lessonPlan->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('student.lesson-plans.show');
        $showResponse->assertViewHas('lessonPlan', fn ($viewLessonPlan) => $viewLessonPlan->id === $lessonPlan->id);
    }

    public function test_an_unrelated_student_gets_a_404_on_show(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $lessonPlan = $this->makeLessonPlan($school, $academicYear, $classSection, $subject, $teacher);

        $unrelatedStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($unrelatedStudent)->get("/student/lesson-plans/{$lessonPlan->id}");

        $response->assertNotFound();
    }
}
