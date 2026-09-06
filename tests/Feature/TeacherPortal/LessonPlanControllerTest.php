<?php

namespace Tests\Feature\TeacherPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §5, §6
 *
 * TeacherPortal\LessonPlanController's HTTP-layer coverage — see
 * TeacherPortal\TimetableControllerTest's doc comment for the shape
 * shared across all four generic Phase 4 resource controller test files.
 * Covers LessonPlanRepository::create() only, not approve()/reject() —
 * those are LessonPlanRepositoryTest's territory and unrelated to this
 * pass's read + teacher-create routing/testing scope.
 */
class LessonPlanControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/teacher/lesson-plans', []);

        $response->assertRedirect('/login');
    }

    public function test_non_teacher_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post('/teacher/lesson-plans', []);

        $response->assertForbidden();
    }

    /**
     * The "New lesson plan" view is a plain `<form method="POST">` with
     * no fetch(), so a successful submit must redirect rather than
     * navigate the browser to a raw JSON body — matching
     * TeacherPortal\TimetableControllerTest's identical
     * assertRedirect()-over-assertCreated() shape for the same reason
     * (see TimetableController::store()'s own doc comment).
     */
    public function test_teacher_can_create_a_lesson_plan_for_their_own_assigned_class_section(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);

        $response = $this->actingAs($teacher)->post('/teacher/lesson-plans', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'title' => 'Photosynthesis',
            'content' => 'Chapter 4 walkthrough with a lab demo.',
        ]);

        $response->assertRedirect(route('teacher.lesson-plans.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('lesson_plans', [
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Photosynthesis',
            'status' => 'draft',
        ]);
    }

    /**
     * A fetch()/API caller (Accept: application/json, via postJson())
     * keeps the original JSON 201 contract untouched by the wantsJson()
     * branch above.
     */
    public function test_a_json_caller_still_gets_a_201_with_the_created_lesson_plan(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);

        $response = $this->actingAs($teacher)->postJson('/teacher/lesson-plans', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'title' => 'Photosynthesis',
            'content' => 'Chapter 4 walkthrough with a lab demo.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.class_section_id', $classSection->id);
        $response->assertJsonPath('data.teacher_id', $teacher->id);
        $response->assertJsonPath('data.status', 'draft');
    }

    public function test_a_teacher_not_assigned_to_the_class_section_gets_a_404_not_a_403_or_422(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $ownerTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $ownerTeacher);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($otherTeacher)->post('/teacher/lesson-plans', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'title' => 'Not my section',
            'content' => 'Should never be created.',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('lesson_plans', 0);
    }

    public function test_teacher_can_index_and_show_lesson_plans_for_their_own_assigned_sections(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $lessonPlan = $this->makeLessonPlan($school, $academicYear, $classSection, $subject, $teacher);

        $indexResponse = $this->actingAs($teacher)->get('/teacher/lesson-plans');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('teacher.lesson-plans.index');
        $indexResponse->assertViewHas('lessonPlans', fn ($lessonPlans) => $lessonPlans->contains('id', $lessonPlan->id));

        $showResponse = $this->actingAs($teacher)->get("/teacher/lesson-plans/{$lessonPlan->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('teacher.lesson-plans.show');
        $showResponse->assertViewHas('lessonPlan', fn ($viewLessonPlan) => $viewLessonPlan->id === $lessonPlan->id);
    }

    public function test_a_teacher_with_no_assignment_to_the_plans_section_gets_a_404_on_show(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $ownerTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $ownerTeacher);
        $lessonPlan = $this->makeLessonPlan($school, $academicYear, $classSection, $subject, $ownerTeacher);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($otherTeacher)->get("/teacher/lesson-plans/{$lessonPlan->id}");

        $response->assertNotFound();
    }

    /**
     * Cross-tenant: see TimetableControllerTest's identical test for why
     * $classSection is deliberately built with $teacher as its homeroom
     * teacher despite belonging to $otherSchool — the fixture shape
     * needed to reach LessonPlanRepository::create()'s own school_id
     * comparison rather than a request-validation rejection.
     */
    public function test_a_class_section_from_another_school_is_rejected_with_a_422(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $otherAcademicYear = $this->makeAcademicYear($otherSchool);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $subject = $this->makeSubject($school);
        $classSectionInOtherSchool = $this->makeClassSection($otherSchool, $otherAcademicYear, $teacher);

        $response = $this->actingAs($teacher)->post('/teacher/lesson-plans', [
            'class_section_id' => $classSectionInOtherSchool->id,
            'subject_id' => $subject->id,
            'title' => 'Cross tenant',
            'content' => 'Should never be created.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('class_section_id');
        $this->assertDatabaseCount('lesson_plans', 0);
    }

    /**
     * Same repository-level rejection as the test above, through the
     * JSON path a fetch()/API caller uses instead of session flash —
     * see TeacherPortal\TimetableControllerTest's identical
     * postJson()/assertStatus(422) counterpart test.
     */
    public function test_a_json_caller_gets_a_422_for_a_class_section_from_another_school(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $otherAcademicYear = $this->makeAcademicYear($otherSchool);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $subject = $this->makeSubject($school);
        $classSectionInOtherSchool = $this->makeClassSection($otherSchool, $otherAcademicYear, $teacher);

        $response = $this->actingAs($teacher)->postJson('/teacher/lesson-plans', [
            'class_section_id' => $classSectionInOtherSchool->id,
            'subject_id' => $subject->id,
            'title' => 'Cross tenant',
            'content' => 'Should never be created.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['class_section_id']]);
        $this->assertDatabaseCount('lesson_plans', 0);
    }
}
