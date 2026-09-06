<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §4, §5, §6 (F30, F31)
 *
 * Admin\LessonPlanController's HTTP-layer coverage — the tenant+role
 * check itself is exercised directly against LessonPlanRepository in
 * Phase4TestGateTest's row 4; this file confirms the controller/route
 * wiring around it (redirect-with-status on success, 404 on an
 * unauthorized review attempt, matching this bundle's other Admin\*
 * controllers' web-form response shape).
 */
class LessonPlanControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/admin/lesson-plans/1/approve', []);

        $response->assertRedirect('/login');
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($teacher)->post('/admin/lesson-plans/1/approve', []);

        $response->assertForbidden();
    }

    public function test_school_admin_can_open_the_lesson_document_upload_form(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->get('/admin/lesson-plans/create');

        $response->assertOk();
        $response->assertViewIs('admin.lesson-plans.create');
        $response->assertViewHas('classSections', fn ($classSections) => $classSections->contains('id', $classSection->id));
        $response->assertViewHas('subjects', fn ($subjects) => $subjects->contains('id', $subject->id));
    }

    public function test_school_admin_can_upload_a_lesson_document_for_their_own_school_class_section(): void
    {
        Storage::fake('local');

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->post('/admin/lesson-plans', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'title' => 'Admin uploaded notes',
            'content' => 'Use with the first revision class.',
            'document' => UploadedFile::fake()->create('revision.pdf', 120, 'application/pdf'),
        ]);

        $response->assertRedirect(route('admin.lesson-plans.create'));
        $response->assertSessionHas('status');

        $lessonPlan = \App\Models\LessonPlan::firstOrFail();

        $this->assertSame($school->id, $lessonPlan->school_id);
        $this->assertSame($academicYear->id, $lessonPlan->academic_year_id);
        $this->assertSame($classSection->id, $lessonPlan->class_section_id);
        $this->assertSame($subject->id, $lessonPlan->subject_id);
        $this->assertSame($admin->id, $lessonPlan->teacher_id);
        $this->assertSame('approved', $lessonPlan->status);
        $this->assertSame($admin->id, $lessonPlan->reviewed_by);
        $this->assertNotNull($lessonPlan->document_path);
        Storage::disk('local')->assertExists($lessonPlan->document_path);
    }

    public function test_school_admin_cannot_upload_a_lesson_document_for_another_schools_class_section(): void
    {
        Storage::fake('local');

        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($otherSchool);
        $teacher = $this->makeRoleUser('teacher', $otherSchool);
        $classSection = $this->makeClassSection($otherSchool, $academicYear, $teacher);
        $subject = $this->makeSubject($otherSchool);
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->post('/admin/lesson-plans', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'title' => 'Cross tenant notes',
            'content' => 'Should not be created.',
            'document' => UploadedFile::fake()->create('revision.pdf', 120, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('lesson_plan');
        $this->assertDatabaseCount('lesson_plans', 0);
        $this->assertCount(0, Storage::disk('local')->files('lesson-plans'));
    }

    public function test_school_admin_can_approve_a_lesson_plan_in_their_own_school(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $lessonPlan = $this->makeLessonPlan($school, $academicYear, $classSection, $subject, $teacher, ['status' => 'submitted']);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post("/admin/lesson-plans/{$lessonPlan->id}/approve", [
            'note' => 'Looks good.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lesson_plans', [
            'id' => $lessonPlan->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_school_admin_can_reject_a_lesson_plan_in_their_own_school(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $lessonPlan = $this->makeLessonPlan($school, $academicYear, $classSection, $subject, $teacher, ['status' => 'submitted']);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post("/admin/lesson-plans/{$lessonPlan->id}/reject", [
            'reason' => 'Needs more detail.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lesson_plans', [
            'id' => $lessonPlan->id,
            'status' => 'rejected',
            'review_note' => 'Needs more detail.',
        ]);
    }

    public function test_a_school_admin_from_another_school_gets_a_404_approving_a_lesson_plan(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $lessonPlan = $this->makeLessonPlan($school, $academicYear, $classSection, $subject, $teacher, ['status' => 'submitted']);
        $otherSchoolAdmin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $otherSchool->id]);

        $response = $this->actingAs($otherSchoolAdmin)->post("/admin/lesson-plans/{$lessonPlan->id}/approve", [
            'note' => 'Should not work.',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('lesson_plans', ['id' => $lessonPlan->id, 'status' => 'submitted']);
    }

    public function test_reject_requires_a_reason(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $lessonPlan = $this->makeLessonPlan($school, $academicYear, $classSection, $subject, $teacher, ['status' => 'submitted']);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post("/admin/lesson-plans/{$lessonPlan->id}/reject", []);

        $response->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('lesson_plans', ['id' => $lessonPlan->id, 'status' => 'submitted']);
    }
}
