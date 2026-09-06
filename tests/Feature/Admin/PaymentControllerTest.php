<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 21-schoolos-finance-architecture.md §3,
 * 22-schoolos-finance-schema.md §4, §6.
 *
 * Admin\PaymentController's HTTP-layer coverage — the manual-payment
 * recording surface (discovery §10.2: cash, bank transfer, a receipt
 * handed in person). Resolves {feeAssessment} through
 * ScopeService::tenantScope() before writing, per 22 §6's write-path
 * obligation for this route.
 */
class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category);

        $response = $this->post("/admin/fee-assessments/{$assessment->id}/payments", ['amount' => '100.00']);

        $response->assertRedirect('/login');
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category);

        $response = $this->actingAs($teacher)->post("/admin/fee-assessments/{$assessment->id}/payments", ['amount' => '100.00']);

        $response->assertForbidden();
    }

    public function test_admin_can_record_a_manual_payment(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);

        $response = $this->actingAs($admin)->post("/admin/fee-assessments/{$assessment->id}/payments", [
            'amount' => '1000.00',
        ]);

        $response->assertRedirect(route('admin.fee-assessments.show', $assessment));
        $this->assertDatabaseHas('payments', [
            'fee_assessment_id' => $assessment->id,
            'amount' => '1000.00',
            'processor' => 'manual',
            'status' => 'confirmed',
            'recorded_by' => $admin->id,
        ]);
        $this->assertSame('paid', $assessment->fresh()->status);
    }

    public function test_a_payment_exceeding_amount_remaining_is_rejected_without_writing_a_row(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '500.00']);

        $response = $this->actingAs($admin)->post("/admin/fee-assessments/{$assessment->id}/payments", [
            'amount' => '500.01',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('open', $assessment->fresh()->status);
    }

    /**
     * The write-side scope obligation 22 §6 calls out: an admin cannot
     * even resolve, let alone record a payment against, an assessment
     * belonging to a different school.
     */
    public function test_admin_cannot_record_a_payment_against_another_schools_assessment(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $otherYear = $this->makeAcademicYear($otherSchool);
        $otherStudent = $this->makeRoleUser('student', $otherSchool);
        $otherCategory = $this->makeFeeCategory($otherSchool);
        $assessmentInOtherSchool = $this->makeFeeAssessment($otherSchool, $otherYear, $otherStudent, $otherCategory);

        $response = $this->actingAs($admin)->post("/admin/fee-assessments/{$assessmentInOtherSchool->id}/payments", [
            'amount' => '100.00',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }
}