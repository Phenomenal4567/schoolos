<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 21-schoolos-finance-architecture.md §2,
 * 22-schoolos-finance-schema.md §2, §6.
 * Decision ref: 16-schoolos-decisions-register.md D11.
 *
 * Admin\FeeAssessmentController's HTTP-layer coverage — index/show via
 * ScopeService::tenantScope(), store()/adjust() via
 * FeeAssessmentRepository. D11's actual amount_due-computation
 * invariants are exercised more thoroughly at the repository level in
 * FeeAssessmentRepositoryTest; this file confirms the controller wires
 * validation, the repository, and tenant scoping together correctly.
 */
class FeeAssessmentControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/fee-assessments');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $response = $this->actingAs($teacher)->get('/admin/fee-assessments');

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_fee_assessment(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);

        $response = $this->actingAs($admin)->post('/admin/fee-assessments', [
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'fee_category_id' => $category->id,
            'base_amount' => '1500.00',
        ]);

        $response->assertRedirect(route('admin.fee-assessments.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fee_assessments', [
            'school_id' => $school->id,
            'student_id' => $student->id,
            'fee_category_id' => $category->id,
            'base_amount' => '1500.00',
            'amount_due' => '1500.00',
        ]);
    }

    public function test_admin_can_create_a_fee_assessment_with_a_discount(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);

        $response = $this->actingAs($admin)->post('/admin/fee-assessments', [
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'fee_category_id' => $category->id,
            'base_amount' => '1000.00',
            'discount_type' => 'percentage',
            'discount_value' => '20',
            'discount_reason' => 'Sibling discount',
        ]);

        $response->assertRedirect(route('admin.fee-assessments.index'));
        $this->assertDatabaseHas('fee_assessments', [
            'school_id' => $school->id,
            'student_id' => $student->id,
            'discount_amount' => '200.00',
            'amount_due' => '800.00',
        ]);
        $this->assertDatabaseHas('discounts', [
            'type' => 'percentage',
            'reason' => 'Sibling discount',
            'granted_by' => $admin->id,
        ]);
    }

    public function test_assessing_a_non_student_target_fails_with_a_validation_error_not_a_500(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $category = $this->makeFeeCategory($school);

        $response = $this->actingAs($admin)->post('/admin/fee-assessments', [
            'academic_year_id' => $academicYear->id,
            'student_id' => $teacher->id,
            'fee_category_id' => $category->id,
            'base_amount' => '1000.00',
        ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('fee_assessments', 0);
    }

    public function test_a_fee_category_from_another_school_fails_request_validation(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $categoryInOtherSchool = $this->makeFeeCategory($otherSchool);

        $response = $this->actingAs($admin)->post('/admin/fee-assessments', [
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'fee_category_id' => $categoryInOtherSchool->id,
            'base_amount' => '1000.00',
        ]);

        $response->assertSessionHasErrors('fee_category_id');
        $this->assertDatabaseCount('fee_assessments', 0);
    }

    public function test_admin_index_only_lists_their_own_schools_assessments(): void
    {
        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();
        $adminA = $this->makeRoleUser('school_admin', $schoolA);
        $yearA = $this->makeAcademicYear($schoolA);
        $yearB = $this->makeAcademicYear($schoolB);
        $studentA = $this->makeRoleUser('student', $schoolA);
        $studentB = $this->makeRoleUser('student', $schoolB);
        $categoryA = $this->makeFeeCategory($schoolA);
        $categoryB = $this->makeFeeCategory($schoolB);

        $assessmentA = $this->makeFeeAssessment($schoolA, $yearA, $studentA, $categoryA);
        $this->makeFeeAssessment($schoolB, $yearB, $studentB, $categoryB);

        $response = $this->actingAs($adminA)->get('/admin/fee-assessments');

        $response->assertOk();
        $response->assertViewHas('assessments', function ($assessments) use ($assessmentA) {
            return $assessments->count() === 1 && $assessments->first()->id === $assessmentA->id;
        });
    }

    public function test_admin_can_view_a_single_assessment_in_their_own_school(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1200.00']);

        $response = $this->actingAs($admin)->get("/admin/fee-assessments/{$assessment->id}");

        $response->assertOk();
        $response->assertViewHas('assessment', fn ($viewAssessment) => $viewAssessment->id === $assessment->id);
        $response->assertViewHas('amountRemaining', '1200.00');
    }

    public function test_admin_cannot_view_another_schools_fee_assessment(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $otherYear = $this->makeAcademicYear($otherSchool);
        $otherStudent = $this->makeRoleUser('student', $otherSchool);
        $otherCategory = $this->makeFeeCategory($otherSchool);
        $assessmentInOtherSchool = $this->makeFeeAssessment($otherSchool, $otherYear, $otherStudent, $otherCategory);

        $response = $this->actingAs($admin)->get("/admin/fee-assessments/{$assessmentInOtherSchool->id}");

        $response->assertNotFound();
    }

    /**
     * D11's escape hatch, exercised end to end through the HTTP layer:
     * a discount granted after payments already exist must not rewrite
     * the original assessment's amount_due.
     */
    public function test_adjust_records_a_new_credit_line_without_mutating_the_original_amount_due(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);
        $this->makePayment($school, $assessment, ['amount' => '400.00', 'status' => 'confirmed']);

        $response = $this->actingAs($admin)->post("/admin/fee-assessments/{$assessment->id}/adjust", [
            'discount_type' => 'fixed',
            'discount_value' => '150.00',
        ]);

        $response->assertRedirect(route('admin.fee-assessments.show', $assessment));
        $this->assertDatabaseHas('fee_assessments', [
            'id' => $assessment->id,
            'amount_due' => '1000.00',
        ]);
        $this->assertDatabaseHas('fee_assessments', [
            'student_id' => $student->id,
            'base_amount' => '-150.00',
            'amount_due' => '-150.00',
        ]);
    }

    public function test_admin_cannot_adjust_another_schools_fee_assessment(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $otherYear = $this->makeAcademicYear($otherSchool);
        $otherStudent = $this->makeRoleUser('student', $otherSchool);
        $otherCategory = $this->makeFeeCategory($otherSchool);
        $assessmentInOtherSchool = $this->makeFeeAssessment($otherSchool, $otherYear, $otherStudent, $otherCategory);

        $response = $this->actingAs($admin)->post("/admin/fee-assessments/{$assessmentInOtherSchool->id}/adjust", [
            'discount_type' => 'fixed',
            'discount_value' => '50.00',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('discounts', 0);
    }
}